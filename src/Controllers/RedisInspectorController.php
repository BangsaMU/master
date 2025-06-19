<?php

namespace Bangsamu\Master\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class RedisInspectorController extends Controller
{
    public function index(Request $request)
    {
        $pattern = $request->get('pattern', '*');
        $keys = Redis::keys($pattern); // hati-hati di production

        $data = [];
        foreach ($keys as $key) {
            // $type = Redis::type($key);
            $type = Redis::command('type', [$key]);

            $value = null;

            switch ((string)$type) {
                case 'string':
                    $value = Redis::get($key);
                    break;
                case 'list':
                    $value = Redis::lrange($key, 0, -1);
                    break;
                case 'set':
                    $value = Redis::smembers($key);
                    break;
                case 'hash':
                    $value = Redis::hgetall($key);
                    break;
                case 'zset':
                    $value = Redis::zrange($key, 0, -1, ['withscores' => true]);
                    break;
                default:
                    $value = '(unsupported type)';
            }

            $data[] = [
                'key' => $key,
                'type' => $type,
                'value' => $value,
            ];
        }
        // dd($data, $pattern);
        return view('master::redis.inspector', compact('data', 'pattern'));
    }
}
