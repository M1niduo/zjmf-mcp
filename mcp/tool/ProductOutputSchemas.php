<?php

namespace addons\mcp\tool;

class ProductOutputSchemas
{
    public static function all()
    {
        $timestamp = [
            'type' => ['string', 'null'],
            'description' => 'ISO 8601 时间；数据库时间戳为 0 或空时返回 null。',
        ];
        $integerId = [
            'type' => 'integer',
            'description' => '数据库记录 ID。',
        ];
        $optionalText = [
            'type' => ['string', 'null'],
            'description' => '可选文本；没有值时返回 null 或空字符串。',
        ];

        $productSummary = [
            'type' => 'object',
            'description' => '产品列表中的单个产品摘要。',
            'properties' => [
                'id' => $integerId,
                'type' => ['type' => 'string', 'description' => '产品类型机器值，例如 cloud、dcim、hostingaccount。'],
                'gid' => ['type' => 'integer', 'description' => '所属二级产品分组 ID。'],
                'name' => ['type' => 'string', 'description' => '产品名称。'],
                'description' => ['type' => 'string', 'description' => '产品描述，可包含长文本。'],
                'hidden' => ['type' => 'integer', 'description' => '是否隐藏：1 隐藏，0 显示。'],
                'retired' => ['type' => 'integer', 'description' => '是否下架：1 已下架，0 未下架。'],
                'stock_control' => ['type' => 'integer', 'description' => '是否启用库存控制：1 启用，0 不启用。'],
                'qty' => ['type' => 'integer', 'description' => '当前库存数量。'],
                'order' => ['type' => 'integer', 'description' => '产品在分组内的排序值，数值越小越靠前。'],
                'pay_method' => ['type' => 'string', 'description' => '付款方式：prepayment 预付费，postpaid 后付费。'],
                'auto_setup' => ['type' => 'string', 'description' => '产品购买后的自动开通策略。'],
                'zjmf_api_id' => ['type' => 'integer', 'description' => '关联的上游/资源 API ID；0 表示未关联。'],
                'create_time' => $timestamp,
                'update_time' => $timestamp,
                'group_name' => ['type' => ['string', 'null'], 'description' => '二级产品分组名称。'],
                'first_group_id' => ['type' => ['integer', 'null'], 'description' => '一级产品分组 ID。'],
                'first_group_name' => ['type' => ['string', 'null'], 'description' => '一级产品分组名称。'],
                'host_count' => ['type' => 'integer', 'description' => '使用该产品的主机/业务实例总数。'],
                'active_host_count' => ['type' => 'integer', 'description' => '使用该产品且状态为 Active 的实例数量。'],
            ],
            'required' => ['id', 'type', 'gid', 'name', 'description', 'hidden', 'retired', 'stock_control', 'qty', 'order', 'host_count', 'active_host_count'],
        ];

        $pricingCycle = [
            'type' => 'object',
            'description' => '某个计费周期的价格。',
            'properties' => [
                'enabled' => ['type' => 'boolean', 'description' => '该周期是否允许购买；false 表示不可购买。'],
                'price' => ['type' => ['string', 'null'], 'description' => '该周期产品价格，保留两位小数。'],
                'setup_fee' => ['type' => ['string', 'null'], 'description' => '该周期初装费，保留两位小数。'],
            ],
            'required' => ['enabled', 'price', 'setup_fee'],
        ];

        return [
            'product_list' => [
                'type' => 'object',
                'description' => '产品分页查询结果。',
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'description' => '当前页产品列表。',
                        'items' => $productSummary,
                    ],
                    'pagination' => [
                        'type' => 'object',
                        'description' => '分页信息。',
                        'properties' => [
                            'page' => ['type' => 'integer', 'description' => '当前页码，从 1 开始。'],
                            'page_size' => ['type' => 'integer', 'description' => '每页记录数。'],
                            'total' => ['type' => 'integer', 'description' => '符合条件的产品总数。'],
                            'total_pages' => ['type' => 'integer', 'description' => '总页数。'],
                        ],
                        'required' => ['page', 'page_size', 'total', 'total_pages'],
                    ],
                ],
                'required' => ['items', 'pagination'],
            ],
            'product_get' => [
                'type' => 'object',
                'description' => '单个产品详情。',
                'properties' => [
                    'id' => $integerId,
                    'type' => ['type' => 'string', 'description' => '产品类型机器值。'],
                    'gid' => ['type' => 'integer', 'description' => '二级产品分组 ID。'],
                    'name' => ['type' => 'string', 'description' => '产品名称。'],
                    'description' => ['type' => 'string', 'description' => '产品描述长文本。'],
                    'hidden' => ['type' => 'integer', 'description' => '是否隐藏：1 隐藏，0 显示。'],
                    'stock_control' => ['type' => 'integer', 'description' => '是否启用库存控制。'],
                    'qty' => ['type' => 'integer', 'description' => '库存数量。'],
                    'pay_type' => [
                        'type' => 'object',
                        'description' => '产品计费策略。',
                        'properties' => [
                            'pay_type' => ['type' => 'string', 'description' => '计费类型：free、onetime、recurring、day 或 hour。'],
                            'pay_hour_cycle' => ['type' => 'integer', 'description' => '按小时计费时的周期小时数。'],
                            'pay_day_cycle' => ['type' => 'integer', 'description' => '按天计费时的周期天数。'],
                            'pay_ontrial_status' => ['type' => 'integer', 'description' => '是否启用试用。'],
                            'pay_ontrial_cycle' => ['type' => 'integer', 'description' => '试用周期数值。'],
                            'pay_ontrial_cycle_type' => ['type' => 'string', 'description' => '试用周期单位。'],
                            'pay_ontrial_condition' => ['type' => 'array', 'description' => '允许试用的客户条件。'],
                        ],
                    ],
                    'pay_method' => ['type' => 'string', 'description' => 'prepayment 预付费或 postpaid 后付费。'],
                    'allow_qty' => ['type' => 'integer', 'description' => '是否允许一次购买多个。'],
                    'auto_setup' => ['type' => 'string', 'description' => '购买后的自动开通策略。'],
                    'server_type' => ['type' => 'string', 'description' => '服务开通模块类型。'],
                    'server_group' => ['type' => 'integer', 'description' => '服务模块使用的服务器组 ID。'],
                    'recurring_cycles' => ['type' => 'integer', 'description' => '循环扣款次数限制；0 表示不限制。'],
                    'auto_terminate_days' => ['type' => 'integer', 'description' => '开通后自动终止的天数；0 表示不自动终止。'],
                    'auto_terminate_email' => ['type' => 'integer', 'description' => '自动终止通知邮件模板 ID。'],
                    'config_options_upgrade' => ['type' => 'integer', 'description' => '是否允许升级可配置选项。'],
                    'billing_cycle_upgrade' => ['type' => 'string', 'description' => '允许升级到的计费周期配置。'],
                    'upgrade_email' => ['type' => 'integer', 'description' => '升级通知邮件模板 ID。'],
                    'order' => ['type' => 'integer', 'description' => '产品排序值。'],
                    'retired' => ['type' => 'integer', 'description' => '是否下架。'],
                    'is_featured' => ['type' => 'integer', 'description' => '是否作为推荐产品展示。'],
                    'create_time' => $timestamp,
                    'update_time' => $timestamp,
                    'group_name' => $optionalText,
                    'first_group_id' => ['type' => ['integer', 'null'], 'description' => '一级产品分组 ID。'],
                    'first_group_name' => $optionalText,
                    'host_count' => ['type' => 'integer', 'description' => '使用该产品的实例总数。'],
                    'active_host_count' => ['type' => 'integer', 'description' => '使用该产品的 Active 实例数量。'],
                    'pricing' => [
                        'type' => 'array',
                        'description' => '各货币的产品价格。',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'currency_id' => $integerId,
                                'currency_code' => ['type' => 'string', 'description' => '货币代码。'],
                                'is_default' => ['type' => 'boolean', 'description' => '是否为默认货币。'],
                                'cycles' => [
                                    'type' => 'object',
                                    'description' => '按计费周期索引的价格集合。',
                                    'additionalProperties' => $pricingCycle,
                                ],
                            ],
                            'required' => ['currency_id', 'currency_code', 'is_default', 'cycles'],
                        ],
                    ],
                    'config_group_ids' => ['type' => 'array', 'description' => '产品关联的可配置选项组 ID。', 'items' => $integerId],
                    'upgrade_product_ids' => ['type' => 'array', 'description' => '允许升级到的产品 ID。', 'items' => $integerId],
                    'custom_field_count' => ['type' => 'integer', 'description' => '产品自定义字段数量。'],
                    'download_count' => ['type' => 'integer', 'description' => '产品关联下载资源数量。'],
                    'product_shopping_url' => ['type' => 'string', 'description' => '前台产品购买链接。'],
                    'product_group_url' => ['type' => 'string', 'description' => '前台产品分组链接。'],
                ],
                'required' => ['id', 'type', 'gid', 'name', 'description', 'pay_type', 'pay_method', 'pricing', 'host_count', 'active_host_count'],
                'additionalProperties' => true,
            ],
            'product_group_get' => [
                'type' => 'object',
                'description' => '商品组详情。',
                'properties' => [
                    'id' => $integerId,
                    'gid' => ['type' => 'integer', 'description' => '所属一级商品组 ID。'],
                    'name' => ['type' => 'string', 'description' => '商品组名称。'],
                    'headline' => ['type' => 'string', 'description' => '商品组标题。'],
                    'tagline' => ['type' => 'string', 'description' => '商品组标语。'],
                    'hidden' => ['type' => 'integer', 'description' => '是否隐藏：1 隐藏，0 显示。'],
                    'order' => ['type' => 'integer', 'description' => '商品组排序值。'],
                    'alias' => ['type' => 'string', 'description' => '商品组别名/访问标识。'],
                    'tpl_type' => ['type' => 'string', 'description' => '订购模板类型。'],
                    'is_upstream' => ['type' => 'integer', 'description' => '是否为上游商品组。'],
                    'zjfm_api_id' => ['type' => 'integer', 'description' => '关联的上游 API ID。'],
                    'create_time' => $timestamp,
                    'update_time' => $timestamp,
                    'custom_fields' => [
                        'type' => 'object',
                        'description' => '商品组固定自定义字段。',
                        'properties' => [
                            'badge' => ['type' => 'string', 'description' => '商品组展示角标/徽标文本。'],
                            'seo_desc' => ['type' => 'string', 'description' => '商品组 SEO 描述文本。'],
                        ],
                        'required' => ['badge', 'seo_desc'],
                    ],
                ],
                'required' => ['id', 'gid', 'name', 'headline', 'tagline', 'custom_fields'],
            ],
            'product_group_update' => [
                'type' => 'object',
                'description' => '商品组修改结果或修改预览。',
                'properties' => [
                    'updated' => ['type' => 'boolean', 'description' => '是否已经写入数据库；dry-run 时为 false。'],
                    'dry_run' => ['type' => 'boolean', 'description' => '本次是否只是修改预览。'],
                    'group_id' => ['type' => 'integer', 'description' => '商品组 ID。'],
                    'changed_fields' => ['type' => 'array', 'description' => '发生变化的普通字段名。', 'items' => ['type' => 'string']],
                    'changed_custom_fields' => ['type' => 'array', 'description' => '发生变化的自定义字段名。', 'items' => ['type' => 'string']],
                    'changes' => ['type' => 'object', 'description' => 'dry-run 返回的普通字段新值。'],
                    'custom_field_changes' => ['type' => 'object', 'description' => 'dry-run 返回的自定义字段新值。'],
                    'warnings' => ['type' => 'array', 'description' => '写入成功后的非致命警告。', 'items' => ['type' => 'string']],
                ],
                'required' => ['updated', 'dry_run', 'group_id', 'changed_fields', 'changed_custom_fields'],
            ],
            'product_group_tree' => [
                'type' => 'object',
                'description' => '商品组层级树。',
                'properties' => [
                    'tree' => [
                        'type' => 'array',
                        'description' => '一级商品组列表。',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => $integerId,
                                'name' => ['type' => 'string', 'description' => '一级商品组名称。'],
                                'hidden' => ['type' => 'integer', 'description' => '一级商品组是否隐藏。'],
                                'order' => ['type' => 'integer', 'description' => '一级商品组排序值。'],
                                'create_time' => $timestamp,
                                'update_time' => $timestamp,
                                'groups' => [
                                    'type' => 'array',
                                    'description' => '该一级组下的二级商品组。',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => $integerId,
                                            'gid' => ['type' => 'integer', 'description' => '所属一级商品组 ID。'],
                                            'name' => ['type' => 'string', 'description' => '商品组名称。'],
                                            'headline' => ['type' => 'string', 'description' => '商品组标题。'],
                                            'tagline' => ['type' => 'string', 'description' => '商品组标语。'],
                                            'hidden' => ['type' => 'integer', 'description' => '商品组是否隐藏。'],
                                            'order' => ['type' => 'integer', 'description' => '商品组排序值。'],
                                            'product_count' => ['type' => 'integer', 'description' => '商品组下的产品数量。'],
                                            'host_count' => ['type' => 'integer', 'description' => '商品组下产品的实例总数。'],
                                            'active_host_count' => ['type' => 'integer', 'description' => '商品组下产品的 Active 实例数量。'],
                                            'create_time' => $timestamp,
                                            'update_time' => $timestamp,
                                        ],
                                        'required' => ['id', 'gid', 'name', 'product_count', 'host_count', 'active_host_count'],
                                    ],
                                ],
                            ],
                            'required' => ['id', 'name', 'groups'],
                        ],
                    ],
                ],
                'required' => ['tree'],
            ],
            'product_update' => [
                'type' => 'object',
                'description' => '产品名称、描述或排序修改结果。',
                'properties' => [
                    'updated' => ['type' => 'boolean', 'description' => '是否已经写入数据库；dry-run 时为 false。'],
                    'dry_run' => ['type' => 'boolean', 'description' => '本次是否只是修改预览。'],
                    'product_id' => ['type' => 'integer', 'description' => '产品 ID。'],
                    'changed_fields' => ['type' => 'array', 'description' => '发生变化的字段名。', 'items' => ['type' => 'string']],
                    'changes' => ['type' => 'object', 'description' => 'dry-run 返回的字段新值。'],
                    'warnings' => ['type' => 'array', 'description' => '写入成功后的非致命警告。', 'items' => ['type' => 'string']],
                ],
                'required' => ['updated', 'dry_run', 'product_id', 'changed_fields'],
            ],
        ];
    }
}
