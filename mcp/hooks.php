<?php

require_once __DIR__ . '/vendor/autoload.php';

use think\facade\Route;

Route::post('mcp', '\addons\mcp\controller\McpController@index');
