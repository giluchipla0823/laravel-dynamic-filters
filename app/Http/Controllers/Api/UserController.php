<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;

class UserController extends Controller
{

    public const FILTER_TYPE_LIKE = 'LIKE';
    public const FILTER_TYPE_EQUAL = 'EQUAL';
    public const FILTER_TYPE_NOT_EQUAL = 'NOT_EQUAL';
    public const FILTER_TYPE_CONTAIN = 'CONTAIN';
    public const FILTER_TYPE_NOT_CONTAIN = 'NOT_CONTAIN';
    public const FILTER_TYPE_GREATER_THAN = 'GREATER_THAN';
    public const FILTER_TYPE_GREATER_THAN_OR_EQUAL = 'GREATER_THAN_OR_EQUAL';
    public const FILTER_TYPE_LESS_THAN = 'LESS_THAN';
    public const FILTER_TYPE_LESS_THAN_OR_EQUAL = 'LESS_THAN_OR_EQUAL';
    public const FILTER_TYPE_BETWEEN = 'BETWEEN';

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $allowFilters = ['id', 'name', 'email', 'email_verified_at', 'posts.user.name'];

        /* @var Model $model */
        $model = User::getModel();

        $table = $model->getTable();

        $columns = Schema::getColumnListing($table);

        $builder = $model->newQuery();

        foreach ($request->get('filters', []) as $field => $data) {
            $keys = explode('.', $field);
            $value = $data['value'] ?? '';
            $type = $data['type'] ?? 'LIKE';

            if (count($keys) === 1) {
                if (!in_array(current($keys), $allowFilters)) {
                    continue;
                }

                $value = $this->parseValue($table, $field, $type, $value);

                switch ($type) {
                    case self::FILTER_TYPE_LIKE:
                    case self::FILTER_TYPE_EQUAL:
                    case self::FILTER_TYPE_NOT_EQUAL:
                        $operator = $this->getOperator($type);

                        $builder->where($field, $operator, $value);
                        break;

                    case self::FILTER_TYPE_CONTAIN:
                        $builder->whereIn($field, $value);
                        break;

                    case self::FILTER_TYPE_NOT_CONTAIN:
                        $builder->whereNotIn($field, $value);
                        break;

                    case self::FILTER_TYPE_BETWEEN:
                        $builder->whereBetween($field, $value);
                        break;
                    default:

                        break;
                }
            } else {

                $lastKey = array_key_last($keys);
                $lastField = $keys[$lastKey];

                unset($keys[$lastKey]);

                $relation = implode('.', $keys);

                $builder->whereHas($relation, function(Builder $query) use ($lastField, $value, $type) {
                    $model = $query->getModel();
                    $table = $model->getTable();

                    $value = $this->parseValue($table, $lastField, $type, $value);

                    $query->where($lastField, 'LIKE', $value);
                });
            }
        }

//        foreach ($request->all() as $field => $value) {
//            $keys = explode('_', $field);
//
//            if (count($keys) === 1) {
//                if (!in_array(current($keys), $allowFilters)) {
//                    continue;
//                }
//
//                $builder->where($field, 'LIKE', "%$value%");
//            } else {
//                if (!in_array(implode('.', $keys), $allowFilters)) {
//                    continue;
//                }
//
//                $lastKey = array_key_last($keys);
//                $lastField = $keys[$lastKey];
//
//                unset($keys[$lastKey]);
//
//                $relation = implode('.', $keys);
//
//                $builder->whereHas($relation, function($query) use ($lastField, $value) {
//                    $query->where($lastField, 'LIKE', "%$value%");
//                });
//            }
//        }

        // dd($builder->toSql());

        $users = $builder->get();



        return response()->json($users);
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $type
     * @param string $value
     * @return mixed
     */
    private function parseValue(string $table, string $column, string $type, string $value): mixed
    {
        $columnType = Schema::getColumnType($table, $column);

        return match ($type) {
            self::FILTER_TYPE_LIKE => "%$value%",
            self::FILTER_TYPE_CONTAIN,
            self::FILTER_TYPE_NOT_CONTAIN => explode(',', $value),
            self::FILTER_TYPE_GREATER_THAN,
            self::FILTER_TYPE_GREATER_THAN_OR_EQUAL,
            self::FILTER_TYPE_LESS_THAN,
            self::FILTER_TYPE_LESS_THAN_OR_EQUAL => $columnType === 'datetime' ? Carbon::parse($value) : $value,
            self::FILTER_TYPE_BETWEEN => (function() use($columnType, $value) {

                if (!$value) {
                    throw new InvalidArgumentException("The value for the search type BETWEEN cannot be empty.");
                }

                $values = explode(",", $value);

                if (count($values) < 2 || empty($values[1])) {
                    throw new InvalidArgumentException("You must specify two comma-separated values for the search type BETWEEN cannot be empty.");
                }

                if ($columnType === 'datetime') {
                    return [
                        Carbon::parse($values[0])->toDateString() . " 00:00:00",
                        Carbon::createFromFormat('Y-m-d', $values[1])->toDateString() . " 23:59:59"
                    ];
                }

                return [
                    $values[0],
                    $values[1]
                ];
            })(),
            default => $value
        };
    }

    /**
     * @param string $type
     * @return string
     */
    private function getOperator(string $type): string
    {
        return match ($type) {
            self::FILTER_TYPE_LIKE => 'LIKE',
            self::FILTER_TYPE_EQUAL => '=',
            self::FILTER_TYPE_NOT_EQUAL => '!=',
            self::FILTER_TYPE_GREATER_THAN => '>',
            self::FILTER_TYPE_GREATER_THAN_OR_EQUAL => '>=',
            self::FILTER_TYPE_LESS_THAN => '<',
            self::FILTER_TYPE_LESS_THAN_OR_EQUAL => '<=',
        };
    }
}
