<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        $allowFilters = ['name', 'email', 'posts'];


        /* @var Model $model */
        $model = User::getModel();
        $table = $model->getTable();
        $columns = Schema::getColumnListing($table);

        // $columns = DB::getSchemaBuilder()->getColumnType($table, 'email');

        // $columns = Schema::getColumnType($table, 'email');

        // $columns = Schema::getConnection()->getDoctrineSchemaManager()->listTableColumns($table);

        $builder = $model->newQuery();

        foreach ($request->all() as $field => $value) {
            $keys = explode('_', $field);

            $first = current($keys);









































            if (!in_array($first, $allowFilters)) {
                continue;
            }

            if (count($keys) === 1) {
                $builder->where($field, 'LIKE', "%$value%");
            } else {
                $lastKey = array_key_last($keys);
                $lastField = $keys[$lastKey];

                unset($keys[$lastKey]);

                $relation = implode('.', $keys);

                $builder->whereHas($relation, function($query) use ($lastField, $value) {
                    $query->where($lastField, 'LIKE', "%$value%");
                });
            }
        }

        $users = $builder->get();

        return response()->json($users);
    }

    private function createNested() {

    }
}
