# MCP Plugin

为魔方财务系统提供 PHP 7.4 兼容的 MCP Server。

仅支持 MCP `2026-07-28` 新协议，不再兼容旧版 MCP 的 `initialize`/Session 握手。

## 约束

- 仅支持 PHP 7.4，不依赖 PHP 8.x。
- 使用 `ixc-soft/mcp-sdk-php` `2.0.0`，依赖安装在插件内部 `vendor/`。
- 产品服务层在插件内部 `service/`（`addons\mcp\service`），不依赖其他插件。
- MCP 地址：`POST /mcp`，每个请求使用 `2026-07-28` 的 `_meta` 协议信封。
- Token 使用后台入口路径，即 `app/config/database.php` 中的 `admin_application`。
- 请求头：`Authorization: Bearer <admin_application>`。
- 当前提供 `product_list`、`product_get`、`product_group_get`、`product_group_update`、`product_group_tree` 和 `product_update`。
- `product_update` 仅允许修改产品名称、描述和排序；`product_group_update` 仅允许修改商品组名称、标题、标语和固定自定义字段 `badge`、`seo_desc`。
- MCP `tools/list` 提供 `outputSchema`，工具调用同时返回 `structuredContent` 和便于人工阅读的文本内容。
- 无数据库表、后台页面和菜单。

## 工具

- `product_list`：按关键字、分组、类型和状态分页查询产品。
- `product_get`：查询单个产品、价格和依赖摘要。
- `product_group_get`：查询商品组名称、标题、标语和固定自定义字段 `badge`、`seo_desc`。
- `product_group_update`：修改商品组名称、标题、标语和 `badge`、`seo_desc`，支持 `dry_run`。
- `product_group_tree`：查询一级、二级产品分组树。
- `product_update`：修改产品名称、描述和排序，支持 `dry_run`。

## 请求格式

`2026-07-28` 请求必须同时携带协议元数据头和 `_meta` 信封。例如 `tools/list`：

```http
POST /mcp
Authorization: Bearer administrator
Content-Type: application/json
Accept: application/json
MCP-Protocol-Version: 2026-07-28
Mcp-Method: tools/list
```

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/list",
  "params": {
    "_meta": {
      "io.modelcontextprotocol/protocolVersion": "2026-07-28",
      "io.modelcontextprotocol/clientCapabilities": {}
    }
  }
}
```

`tools/call` 还必须携带 `Mcp-Name: <工具名>`。缺少协议信封或使用旧版 `initialize`/Session 请求会被拒绝。

## 启用

1. 在插件管理中启用 `MCP Server`。
2. 使用支持 MCP `2026-07-28` 的客户端连接 `https://域名/mcp`。
3. 配置请求头 `Authorization: Bearer administrator`；如果后台入口已修改，请使用对应的 `admin_application` 值。

## 依赖

如需重新安装依赖，在本目录执行：

```bash
composer install
```

Composer 配置中的 PHP 平台固定为 `7.4.33`，用于阻止解析需要 PHP 8.x 的依赖。

## 版本与代理说明

- 插件使用 `ixc-soft/mcp-sdk-php` `2.0.0`，并通过 PHP 7.4 兼容层补齐 `array_is_list()`。
- 插件入口只接受 `2026-07-28` 请求，旧版协议请求会返回 `400`。
- 如果使用 Apache 或反向代理，请确保 `Authorization` 请求头能传递到 PHP；否则 MCP 客户端会持续收到 `401`。
