<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use App\Filter;
use App\News;
use App\Stocks;
use App\User;
use Illuminate\Http\Request;

define('tokenBase', '3dff4ca43f3af248c2222efd7d5c7696');

header("Access-Control-Allow-Origin: https://app.fastbot.pro");
//header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET');

$router->post('/api/filter/save', function (Request $request) use ($router) {
    $json = json_decode($request->getContent(), true);
    if (tokenBase === $json["token"] && isset($json["user"])) {
        if ($json["_id"] === 0) {
            $filter = Filter::create(
                [
                    'title' => $json['brand'] . ' ' . $json['model'],
                    'isActive' => true,
                    'brand' => $json['brand'],
                    'model' => $json['model'],
                    'priceStart' => $json['priceStart'],
                    'priceEnd' => $json['priceEnd'],
                    'region' => $json['region'],
                    'city' => $json['city'],
                    'city_name' => $json['city_name'],
                    'yearStart' => $json['yearStart'],
                    'yearEnd' => $json['yearEnd'],
                    'gearbox' => $json['gearbox'],
                    'telegram_user_id' => "01".$json['user'],
                    'condition' => $json['condition'],
                    'isCleared' => ['type' => $json['isCleared']],
                    'needsPremium' => true,
                ]);
        } else {
            Filter::find($json["_id"])->update(
                [
                    'title' => $json['brand'] . ' ' . $json['model'],
                    'isActive' => true,
                    'brand' => $json['brand'],
                    'model' => $json['model'],
                    'priceStart' => $json['priceStart'],
                    'priceEnd' => $json['priceEnd'],
                    'region' => $json['region'],
                    'city' => $json['city'],
                    'city_name' => $json['city_name'],
                    'yearStart' => $json['yearStart'],
                    'yearEnd' => $json['yearEnd'],
                    'gearbox' => $json['gearbox'],
                    'telegram_user_id' => "01".$json['user'],
                    'condition' => $json['condition'],
                    'isCleared' => ['type' => $json['isCleared']],
                    'needsPremium' => true,
                ]);
            $filter = Filter::findOrFail($json["_id"]);

        }
        if (!empty($filter->_id)) {
            $client = new GuzzleHttp\Client();
            $user = User::where("chat_id",$json['user'])->first();
            $res = $client->post('http://167.99.218.57:3003/api/filter/new/'.$json['user'], [
                'json' => $filter
            ]);
        }
    }
    return $json;
});

// Remove Filter (MongoDB)
$router->post('/api/delete', function (Request $request) use ($router) {
    $json = json_decode($request->getContent(), true);
    if (tokenBase === $json["token"] && isset($json["user"])) {
        Filter::find($json['id'])->delete();
    }
    return $json;
});

// Get Filters (MongoDB)
$router->get('/api/filter', function (Request $request) use ($router) {
    $filters = Filter::where("telegram_user_id", "01".$request->get("telegram_user_id"))->orderBy('created_at', 'desc')->get();
    $counter = 0;
    $tarif = 0;
//    $user = new User();
//    $user->setConnection('mongodbBot');
    $find_user = User::where("chat_id",$request->get("telegram_user_id"))->get();
    if (!empty($find_user->tariff)) {
        if ($find_user->tariff == "standard") {
            $tarif = 1;
        }
        if ($find_user->tariff == "professional") {
            $tarif = 2;
        }
        if ($find_user->tariff == "professional") {
            $tarif = 3;
        }
    }
    $needsPremium = false;
    foreach ($filters as $filter) {
        if ($tarif == 0) {
            $needsPremium = true;
        }
        if ($tarif === 1) {
            if ($counter > 2) {
                $needsPremium = true;
            }
            if ($filter->region === '' || $filter->brand === '' || $filter->model === '') {
                $needsPremium = true;
            }
            if ($filter->condition !== '' || $filter->gearbox !== '' || $filter->isCleared !== null) {
                $needsPremium = true;
            }
            if ($filter->region === '' && $filter->brand === '' && $filter->model === null) {
                $needsPremium = true;
            }
        }
        if ($tarif === 2) {
            if ($filter->condition !== '' || $filter->gearbox !== '' || $filter->isCleared !== null) {
                $needsPremium = true;
            }
        }
        if (!$needsPremium) {
            $filter->isActive = true;
        }
        $filter->needsPremium = $needsPremium;
        $filter->save();
        $counter++;
    }
    return $filters;
});

// Get User (MongoDB)
$router->get('/api/user/{token}', function ($token = null) use ($router) {
    $user = "";
    if ($user !== null) {
        $user = User::where('_id', $token)->get();
    }
    return $user;
});

// Get News (MongoDB)
$router->get('/api/news', function (Request $request) use ($router) {
    if($request->id){
        $news = News::find($request->id);
    }
    else{
        $news = News::take(3)->get();
    }

    return $news;
});
$router->get('/', function () use ($router) {
});


// Get tarif (MongoDB)
$router->get('/api/tarif', function (Request $request) use ($router) {
    $user = User::where("chat_id", "01".$request->user_id)->get();
    return json_encode(["rate"=>$user->tariff]);
});

// Get Stocks (MongoDB)
$router->get('/api/stocks', function () use ($router) {
    $stocks = Stocks::all();
    return $stocks;
});

// Get Brands & Models Auto (FileJSON)
$router->get('/api/brands', function () use ($router) {
    $path = resource_path() . "/json/cars.json";
    $json = json_decode(file_get_contents($path), true);

    $result = [];
    foreach ($json as $item) {
        $brands = [];
        $brands['name'] = $item['brand'];
        $brands['popular'] = false;
        if (array_key_exists('popular', $item)) {
            $brands['popular'] = $item['popular'];
        }
        foreach ($item['model'] as $models) {
            $model = [];
            if (is_array($models)) {
                $model['name'] = $models['name'];
                $model['popular'] = false;
                if (array_key_exists('popular', $models)) {
                    $model['popular'] = $models['popular'];
                }
            } else {
                $model['name'] = $models;
                $model['popular'] = false;
            }
            $brands['model'][] = $model;
        }
        $result[] = $brands;
    }
    return $result;
});

// Get Regions & Cities (FileJSON)
$router->get('/api/regions', function () use ($router) {
    $path = resource_path() . "/json/regions.json";
    $json = json_decode(file_get_contents($path), true);

    $result = [];
    foreach ($json as $item) {
        $region = [];
        $region['name'] = $item['name'];
        if (!array_key_exists('popular', $item)) {
            $region['popular'] = false;
        } else {
            $region['popular'] = $item['popular'];
        }
        foreach ($item['cities'] as $cityes) {
            $city = [];
            $city['name'] = $cityes['name'];
            $city['city'] = isset($cityes['city']) ? $cityes['city'] : $cityes['name'];
            if (!array_key_exists('popular', $cityes)) {
                $city['popular'] = false;
            } else {
                $city['popular'] = $cityes['popular'];
            }
            $region['cities'][] = $city;
        }
        $result[] = $region;
    }
    return $result;
});
