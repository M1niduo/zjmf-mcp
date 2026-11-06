<?php

namespace addons\mcp;

class McpPlugin extends \app\admin\lib\Plugin
{
    public $info = [
        'name' => 'Mcp',
        'title' => 'MCP Server',
        'description' => '基于的产品查询与基础产品管理 MCP Server',
        'status' => 1,
        'author' => '<a href="http://www.3-o.cn">三欧科技</a>',
        'version' => '1.4.0',
        'module' => 'addons',
        'not_install' => true,
    ];

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }
}
