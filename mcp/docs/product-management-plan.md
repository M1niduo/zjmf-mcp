# MCP 产品管理能力规划

> 基于当前魔方财务源码梳理，目标是在现有 MCP 插件中增加产品管理能力。本文只规划能力边界、工具设计和实现约束，不直接把后台控制器原样暴露给 MCP。

## 1. 结论摘要

当前产品管理不是单一的“产品 CRUD”，而是一个包含以下关联域的管理面：

1. 一级产品分组、二级产品分组、产品三级目录。
2. 产品基础资料、上下架、库存、排序、复制和删除。
3. 多货币、多周期价格和初装费。
4. 计费周期、试用、预付费/后付费、自动开通等产品策略。
5. 服务开通模块、服务器组和模块参数。
6. 可配置选项、选项值、联动关系和选项价格。
7. 升级套餐、配置升级和升级邮件。
8. 自定义字段、下载资源、推广佣金和上游产品同步。

因此不建议提供一个巨大的 `update_product` 工具。建议按领域拆成小工具，并先交付“查询 + 基础产品管理 + 价格”，再扩展配置选项和开通模块。

## 2. 当前领域模型

### 2.1 产品目录层级

目录关系为：

```text
product_first_groups
└── product_groups
    └── products
        ├── pricing
        ├── product_config_links
        │   └── product_config_groups
        │       └── product_config_options
        │           └── product_config_options_sub
        │               └── pricing(type=configoptions)
        ├── product_upgrade_products
        ├── customfields(type=product)
        └── product_downloads
```

依据：

- 一级分组表定义：`public/install/thinkcmf.sql:4037-4045`
- 二级产品分组表定义：`public/install/thinkcmf.sql:1517-1530`
- 产品表定义：`public/install/thinkcmf.sql:1555-1622`
- 产品列表按一级分组、二级分组、产品逐层组装：`app/admin/controller/ProductController.php:36-106`
- 产品详情会联查价格、配置组、配置项、自定义字段：`app/common/logic/Product.php:13-53`

### 2.2 产品核心字段

产品核心数据分布在以下几组字段中：

| 能力域 | 主要字段 |
|---|---|
| 基础资料 | `type`, `gid`, `name`, `description`, `order` |
| 销售状态 | `hidden`, `retired`, `is_featured`, `allow_qty` |
| 库存 | `stock_control`, `qty` |
| 计费策略 | `pay_type`, `pay_method`, `recurring_cycles`, `prorata_*` |
| 自动化 | `auto_setup`, `auto_terminate_days`, `auto_terminate_email` |
| 开通模块 | `server_type`, `server_group`, `config_option1..24` |
| 升级 | `config_options_upgrade`, `billing_cycle_upgrade`, `upgrade_email` |
| 上游同步 | `api_type`, `zjmf_api_id`, `upstream_pid`, `upstream_price_type`, `upstream_price_value` |
| 推广 | `affiliateonetime`, `affiliate_pay_type`, `affiliate_pay_amount` |

来源：`public/install/thinkcmf.sql:1555-1622`，以及产品编辑接口的字段读取范围 `app/admin/controller/ProductController.php:1327`。

### 2.3 价格模型

产品价格存放在 `pricing` 表：

- `type=product`：产品价格。
- `type=configoptions`：可配置选项价格。
- `currency`：货币 ID。
- `relid`：产品 ID 或配置项值 ID。
- 同时保存月付、季付、半年、年付、两年至十年等周期费用。
- 每个周期还有对应初装费。
- 负数价格表示该周期不可用。

来源：

- 表结构：`public/install/thinkcmf.sql:1370-1410`
- 价格标准化和负数处理：`app/common/logic/Pricing.php:7-51`
- 保存价格：`app/common/logic/Pricing.php:142-163`
- 后台编辑接口对价格矩阵的定义：`app/admin/controller/ProductController.php:1565-1585`

MCP 工具中不建议继续使用 `-1` 作为魔法值。应暴露：

```json
{
  "currency_id": 1,
  "cycle": "annually",
  "enabled": true,
  "price": "99.00",
  "setup_fee": "0.00"
}
```

服务层负责把 `enabled=false` 转换为 `-1`。

## 3. 现有后台能力盘点

### 3.1 查询能力

当前后台已有以下查询语义：

- 按关键字搜索产品。
- 按一级分组、二级分组展示产品树。
- 返回产品关联的主机数量和 Active 主机数量。
- 返回产品类型、计费类型、库存、开通状态、上游 API 名称。
- 查询完整产品编辑数据，包括产品、分组、货币、价格、配置项、升级关系等。

主要来源：

- 产品列表：`app/admin/controller/ProductController.php:36-106`
- 产品编辑数据：`app/admin/controller/ProductController.php:1314-1371`
- 产品缓存详情：`app/common/logic/Product.php:13-53`

### 3.2 产品生命周期能力

现有产品操作包括：

| 操作 | 现有入口 | 关键约束 |
|---|---|---|
| 创建 | `create()` | 必须有产品类型、二级分组、名称、前台导航页 |
| 编辑 | `edit()` | 一次提交基础、计费、价格、模块、配置项、升级、推广等大量字段 |
| 复制 | `duplicate()` | 复制产品、价格、配置项、联动、自定义字段、升级关系、下载 |
| 删除 | `delete()` | 存在 `host` 记录时禁止删除 |
| 排序 | `updateProductsort()` | 支持组内排序和跨二级分组移动 |
| 库存 | `editStock()` | 单独维护库存开关和数量 |

来源：

- 创建：`app/admin/controller/ProductController.php:1009-1061`
- 复制：`app/admin/controller/ProductController.php:810-941`
- 删除：`app/admin/controller/ProductController.php:666-717`
- 排序：`app/admin/controller/ProductController.php:197-248`
- 库存：`app/admin/controller/ProductController.php:3313-3327`

### 3.3 分组能力

产品分组有两层：

- `product_first_groups`：一级分组。
- `product_groups`：二级产品分组，`gid` 指向一级分组。

现有能力包括创建、编辑、删除、排序和自定义字段。删除约束为：

- 二级分组下存在产品时不能删除。
- 一级分组下存在二级分组时不能删除。
- 一级分组 ID 1 是保留分组，不能删除。

来源：

- 二级分组删除：`app/admin/controller/ProductController.php:728-744`
- 一级分组删除：`app/admin/controller/ProductController.php:755-780`

### 3.4 可配置选项能力

配置选项是独立管理域：

- 配置组：`product_config_groups`
- 产品关联配置组：`product_config_links`
- 配置项：`product_config_options`
- 配置项值：`product_config_options_sub`
- 联动规则：`product_config_options_links`
- 配置项价格：`pricing(type=configoptions)`

现有后台支持配置组 CRUD、复制配置组、配置项 CRUD、选项值 CRUD、联动、排序和 OS 选项配置。

来源：

- 控制器方法清单：`app/admin/controller/ConfigOptionsController.php:31-1566`
- 配置组表：`public/install/thinkcmf.sql:1419-1424`
- 配置项表：`public/install/thinkcmf.sql:1445-1456`
- 配置项值表：`public/install/thinkcmf.sql:1464-1473`

### 3.5 开通模块能力

产品通过 `server_type`、`server_group` 和 `config_option1..24` 绑定服务模块。

当前后台可以：

- 获取可用模块。
- 获取模块元数据。
- 获取模块配置字段结构。
- 将模块配置写入产品或服务器组。

来源：

- 模块列表与元数据：`app/admin/controller/ProvisionController.php:21-48`
- 模块配置结构：`app/admin/controller/ProvisionController.php:52-100`
- 模块配置逻辑：`app/common/logic/Provision.php:46-136`

注意：服务实例开通、暂停、删除等属于主机生命周期，不应放进第一阶段产品管理工具。

### 3.6 写操作必须保留的系统副作用

现有产品写操作不是单纯更新 `products` 表，还必须保留：

1. 数据库事务。
2. 后台审计日志 `active_log()`。
3. 产品 Hook：
   - `product_create`
   - `product_edit`
   - `product_delete`
4. 产品缓存刷新：
   - `Product::updateCache()`
   - `Product::updateListCache()`
   - 删除时清理详情、信息和列表缓存。

来源：

- 创建事务、审计、Hook、缓存：`app/admin/controller/ProductController.php:1039-1060`
- 编辑审计、Hook、缓存：`app/admin/controller/ProductController.php:2443-2446`
- 删除约束、事务、Hook、缓存：`app/admin/controller/ProductController.php:666-717`

## 4. 建议的 MCP 能力

### 4.1 P0：查询和元数据

| 工具 | 能力 |
|---|---|
| `product_metadata_get` | 返回产品类型、计费周期、付款类型、货币、配置选项类型等枚举 |
| `product_group_tree_list` | 返回一级分组、二级分组、产品统计和排序 |
| `product_list` | 分页、关键字、分组、类型、隐藏、下架、库存、上游 API 等过滤 |
| `product_get` | 返回完整产品详情，包括价格、计费策略、模块、配置项摘要、升级关系和依赖计数 |
| `product_dependency_check` | 删除或移动前检查产品、分组、主机、价格、配置项和下载依赖 |

`product_list` 建议参数：

```json
{
  "keyword": "HK",
  "first_group_id": 1,
  "group_id": 92,
  "type": "cloud",
  "hidden": null,
  "retired": null,
  "stock_control": null,
  "api_type": null,
  "page": 1,
  "page_size": 20,
  "order_by": "order",
  "direction": "asc"
}
```

### 4.2 P0：基础产品管理

| 工具 | 能力 |
|---|---|
| `product_create` | 创建最小产品，返回产品 ID |
| `product_update_basic` | 修改名称、分组、描述、显示、下架、推荐、排序 |
| `product_billing_update` | 修改计费类型、预付费/后付费、试用、按天/小时、按比例结算、循环次数 |
| `product_stock_update` | 修改库存开关和数量 |
| `product_move` | 将产品移动到指定二级分组并调整位置 |
| `product_duplicate` | 复制产品及关联配置 |
| `product_delete` | 删除无主机依赖的产品 |
| `product_preview` | 在不落库的情况下返回将修改的字段、冲突、依赖和警告 |

创建最小产品应明确默认值：

- `pay_type=free`
- `pay_method=prepayment`
- `hidden=0`
- `retired=0`
- `stock_control=0`
- 自动排序
- 自动生成前台购买链接
- 创建默认价格记录

这些默认值来自现有创建流程：`app/admin/controller/ProductController.php:1025-1050`。

### 4.3 P0：分组管理

| 工具 | 能力 |
|---|---|
| `product_first_group_upsert` | 创建或修改一级分组 |
| `product_group_upsert` | 创建或修改二级产品分组 |
| `product_group_delete` | 删除空分组 |
| `product_group_reorder` | 组内排序或跨一级分组移动 |

不建议把一级分组和二级分组合成一个工具，因为它们的保留规则、父子约束和删除条件不同。

### 4.4 P1：价格和计费

| 工具 | 能力 |
|---|---|
| `product_price_list` | 按货币列出所有周期价格和初装费 |
| `product_price_update` | 更新指定货币、指定周期的价格和初装费 |
| `product_price_matrix_update` | 一次更新一个货币的完整价格矩阵 |
| `product_billing_preview` | 计算指定周期、货币、配置项组合的展示价格 |

价格工具应满足：

- 金额使用十进制字符串，不使用浮点数。
- 不提供周期时只更新指定字段。
- `enabled=false` 表示周期不可购买。
- 返回 `changed_fields` 和更新后的完整价格矩阵。
- 保留所有启用货币的记录，不只更新默认货币。

### 4.5 P1：开通模块

| 工具 | 能力 |
|---|---|
| `provision_module_list` | 列出可用模块 |
| `provision_module_metadata_get` | 获取模块元数据 |
| `provision_module_schema_get` | 获取模块配置字段定义 |
| `product_provisioning_update` | 修改产品模块、服务器组、自动开通和模块参数 |
| `product_provisioning_validate` | 验证模块、服务器组和参数是否匹配 |

第一阶段只管理“产品如何开通”，不执行 `create`、`suspend`、`terminate` 等主机命令。

### 4.6 P1：可配置选项

| 工具 | 能力 |
|---|---|
| `product_config_group_list` | 列出配置组和产品关联 |
| `product_config_group_upsert` | 创建或修改配置组 |
| `product_config_group_duplicate` | 复制配置组 |
| `product_config_link_update` | 增加、移除或排序产品关联配置组 |
| `product_config_option_upsert` | 创建或修改配置项 |
| `product_config_option_delete` | 删除配置项及选项值 |
| `product_config_option_value_upsert` | 创建或修改选项值 |
| `product_config_option_value_delete` | 删除选项值 |
| `product_config_price_update` | 更新配置项值价格 |
| `product_config_linkage_update` | 修改配置项联动规则 |

这一组工具数量最多，建议单独发布一个版本，不要与基础产品 CRUD 一起首发。

### 4.7 P2：升级、资源和上游

| 能力域 | 建议工具 |
|---|---|
| 升级套餐 | `product_upgrade_targets_get`, `product_upgrade_targets_update` |
| 升级策略 | `product_upgrade_policy_update` |
| 自定义字段 | `product_custom_field_list`, `product_custom_field_upsert`, `product_custom_field_delete` |
| 下载资源 | `product_download_list`, `product_download_link`, `product_download_unlink`, `product_download_upload` |
| 推广佣金 | `product_affiliate_policy_get`, `product_affiliate_policy_update` |
| 上游同步 | `product_upstream_preview_sync`, `product_upstream_sync` |

上游同步属于外部系统写操作，应放在最后，并默认只支持 `preview`，真正同步需要显式 `confirm=true`。

## 5. 工具输入输出规范

所有写工具建议遵循统一约定：

- Patch 语义：只更新调用方明确传入的字段。
- 金额字段使用字符串，例如 `"199.00"`。
- 时间字段返回 ISO 8601。
- 枚举返回稳定机器值，不返回中文展示值。
- 每次写操作支持 `dry_run`。
- 破坏性操作要求 `confirm=true`。
- 返回 `changed_fields`、`warnings`、`dependencies`、`audit_log_id`。
- 支持 `idempotency_key`，避免 MCP 客户端重试导致重复创建产品。
- 支持 `expected_update_time` 或 `expected_revision` 做乐观并发控制。

推荐返回结构：

```json
{
  "ok": true,
  "product_id": 123,
  "changed_fields": ["name", "qty"],
  "warnings": [],
  "dependencies": {
    "active_hosts": 0
  },
  "audit_log_id": 456,
  "data": {}
}
```

## 6. 实现架构建议

### 6.1 不直接调用后台控制器

`app/admin/controller/ProductController.php` 同时处理 HTTP 参数、后台权限、页面数据、数据库、审计、Hook 和缓存。直接从 MCP 调用会带来：

- 后台 Session 依赖。
- 页面专用参数和返回结构。
- 巨大的 `edit()` 输入。
- 权限和业务逻辑耦合。
- 很难做 dry-run 和部分更新。

建议在插件中新增：

```text
public/plugins/addons/mcp/
├── service/
│   ├── ProductQueryService.php
│   ├── ProductWriteService.php
│   ├── ProductPricingService.php
│   ├── ProductGroupService.php
│   ├── ProductConfigOptionService.php
│   ├── ProductProvisioningService.php
│   └── ProductDependencyService.php
├── contract/
│   └── ProductManagementService.php
└── tool/
    ├── ProductReadTools.php
    ├── ProductBasicTools.php
    ├── ProductPricingTools.php
    └── ProductConfigTools.php
```

业务服务复用现有：

- `\app\common\logic\Product`
- `\app\common\logic\Pricing`
- `\app\common\logic\ConfigOptions`
- `\app\common\logic\Provision`
- `\app\admin\validate\ProductsValidate`

### 6.2 写操作处理顺序

每个写工具统一执行：

1. 参数结构校验。
2. 产品类型、分组、货币、模块等引用完整性校验。
3. `dry_run` 时生成差异并返回。
4. 检查依赖和并发版本。
5. 开启数据库事务。
6. 更新主表和关联表。
7. 写 `active_log()`。
8. 触发 `product_create` / `product_edit` / `product_delete`。
9. 刷新产品缓存。
10. 提交事务并返回结果。

事务出错必须回滚，缓存刷新放在事务提交后；现有创建和编辑流程采用这一模式。

## 7. 安全和风险控制

产品管理 MCP 一旦允许写操作，风险远高于当前只读工具。建议：

- 将 `product_*_write`、`product_delete`、`product_upstream_sync` 设置为高风险工具。
- Token 只作为身份凭证，不应继续把后台入口路径当作长期生产密钥。
- 删除工具必须返回依赖预检，并要求二次确认。
- 禁止 MCP 返回数据库密码、服务器密码、模块密钥、邮件账号等敏感配置。
- 模块配置查询需要字段级脱敏。
- 所有写工具必须写审计日志。
- 对创建、复制、同步等工具提供幂等键。
- 对同一产品的并发修改提供乐观锁。

## 8. 推荐交付顺序

### 第一阶段：安全读取和基础管理

1. `product_metadata_get`
2. `product_group_tree_list`
3. `product_list`
4. `product_get`
5. `product_dependency_check`
6. `product_create`
7. `product_update_basic`
8. `product_stock_update`
9. `product_move`
10. `product_duplicate`
11. `product_delete`
12. `product_preview`

### 第二阶段：价格和计费

1. `product_price_list`
2. `product_price_update`
3. `product_price_matrix_update`
4. `product_billing_update`
5. `product_billing_preview`

### 第三阶段：配置选项

1. 配置组 CRUD
2. 产品与配置组关联
3. 配置项和选项值 CRUD
4. 配置项价格
5. 配置项联动

### 第四阶段：模块、升级和资源

1. 开通模块元数据和配置
2. 产品模块参数
3. 升级套餐和升级策略
4. 自定义字段
5. 下载资源
6. 上游同步

## 9. 暂不纳入产品管理的领域

以下能力应继续留在独立领域中：

- 主机实例开通、暂停、恢复、删除、重装。
- 订单、账单、续费和退款。
- 客户、客户分组和客户折扣。
- 促销码和支付网关。
- 服务器和服务器组的运维管理。
- 产品收入和销售报表。

这些能力可以在后续以独立 MCP 工具组加入，避免产品管理工具承担过多副作用。

## 10. 主要源码来源

- `app/admin/controller/ProductController.php:36-106`：产品目录列表。
- `app/admin/controller/ProductController.php:118-248`：分组和产品排序。
- `app/admin/controller/ProductController.php:325-780`：产品分组创建、编辑和删除。
- `app/admin/controller/ProductController.php:666-717`：产品删除及依赖约束。
- `app/admin/controller/ProductController.php:810-941`：产品复制。
- `app/admin/controller/ProductController.php:1009-1061`：产品创建。
- `app/admin/controller/ProductController.php:1314-1371`：产品编辑数据。
- `app/admin/controller/ProductController.php:1565-1585`：价格矩阵接口定义。
- `app/admin/controller/ProductController.php:3313-3327`：库存。
- `app/admin/controller/ConfigOptionsController.php:31-1566`：可配置选项。
- `app/admin/controller/ProvisionController.php:21-100`：开通模块。
- `app/common/logic/Product.php:13-53`：产品聚合详情。
- `app/common/logic/Pricing.php:7-178`：价格标准化、保存和读取。
- `app/admin/validate/ProductsValidate.php:7`：产品字段验证规则。
- `public/install/thinkcmf.sql:1370-1410`：价格表。
- `public/install/thinkcmf.sql:1419-1473`：配置组和配置项表。
- `public/install/thinkcmf.sql:1517-1554`：产品分组和升级关系表。
- `public/install/thinkcmf.sql:1555-1622`：产品主表。
- `public/install/thinkcmf.sql:4037-4045`：一级产品分组表。
