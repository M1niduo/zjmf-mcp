<?php

namespace addons\mcp\tool;

require_once __DIR__ . '/ProductOutputSchemas.php';

use Mcp\Types\CallToolResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\TextContent;
use Mcp\Types\Tool;
use Mcp\Types\ToolAnnotations;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ToolInputSchema;

class ProductTools
{
    private $queryService;
    private $updateService;
    private $groupUpdateService;

    public function __construct($queryService, $updateService, $groupUpdateService)
    {
        $this->queryService = $queryService;
        $this->updateService = $updateService;
        $this->groupUpdateService = $groupUpdateService;
    }

    public function register($server)
    {
        $outputSchemas = ProductOutputSchemas::all();

        $server->registerHandler('tools/list', function ($params) use ($outputSchemas) {
            return new ListToolsResult([
                $this->tool(
                    'product_list',
                    'List products with filters and pagination.',
                    'Product List',
                    [
                        'keyword' => ['type' => 'string', 'description' => 'Product name keyword'],
                        'first_group_id' => ['type' => 'integer', 'description' => 'First-level product group ID'],
                        'group_id' => ['type' => 'integer', 'description' => 'Product group ID'],
                        'type' => ['type' => 'string', 'description' => 'Product type'],
                        'hidden' => ['type' => 'boolean', 'description' => 'Filter hidden products'],
                        'retired' => ['type' => 'boolean', 'description' => 'Filter retired products'],
                        'stock_control' => ['type' => 'boolean', 'description' => 'Filter stock-controlled products'],
                        'page' => ['type' => 'integer', 'minimum' => 1],
                        'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                        'sort_by' => ['type' => 'string', 'enum' => ['id', 'name', 'order', 'qty', 'create_time', 'update_time']],
                        'sort_order' => ['type' => 'string', 'enum' => ['asc', 'desc']],
                    ],
                    [],
                    true,
                    $outputSchemas['product_list']
                ),
                $this->tool(
                    'product_get',
                    'Get one product with pricing and dependency summary.',
                    'Product Detail',
                    ['product_id' => ['type' => 'integer', 'minimum' => 1]],
                    ['product_id'],
                    true,
                    $outputSchemas['product_get']
                ),
                $this->tool(
                    'product_group_get',
                    'Get one product group with badge and seo_desc custom fields.',
                    'Product Group Detail',
                    ['group_id' => ['type' => 'integer', 'minimum' => 1]],
                    ['group_id'],
                    true,
                    $outputSchemas['product_group_get']
                ),
                $this->tool(
                    'product_group_update',
                    'Update product group name, headline, tagline, badge, or seo_desc.',
                    'Update Product Group',
                    [
                        'group_id' => ['type' => 'integer', 'minimum' => 1],
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'headline' => ['type' => 'string', 'maxLength' => 255],
                        'tagline' => ['type' => 'string', 'maxLength' => 255],
                        'custom_fields' => [
                            'type' => 'object',
                            'properties' => [
                                'badge' => ['type' => 'string', 'maxLength' => 255],
                                'seo_desc' => ['type' => 'string', 'maxLength' => 255],
                            ],
                        ],
                        'dry_run' => ['type' => 'boolean'],
                        'expected_update_time' => ['type' => 'integer'],
                    ],
                    ['group_id'],
                    false,
                    $outputSchemas['product_group_update']
                ),
                $this->tool(
                    'product_group_tree',
                    'Get the first-level and second-level product group tree.',
                    'Product Group Tree',
                    ['include_hidden' => ['type' => 'boolean', 'description' => 'Include hidden groups']],
                    [],
                    true,
                    $outputSchemas['product_group_tree']
                ),
                $this->tool(
                    'product_update',
                    'Update only product name, description, or sort order.',
                    'Update Product',
                    [
                        'product_id' => ['type' => 'integer', 'minimum' => 1],
                        'name' => ['type' => 'string', 'maxLength' => 100],
                        'description' => ['type' => 'string'],
                        'order' => ['type' => 'integer'],
                        'dry_run' => ['type' => 'boolean'],
                        'expected_update_time' => ['type' => 'integer'],
                    ],
                    ['product_id'],
                    false,
                    $outputSchemas['product_update']
                ),
            ]);
        });

        $server->registerHandler('tools/call', function ($params) {
            $name = isset($params->name) ? (string) $params->name : '';
            $args = $this->arguments($params);

            try {
                switch ($name) {
                    case 'product_list':
                        return $this->result($this->queryService->list($args));
                    case 'product_get':
                        return $this->result($this->queryService->get($args['product_id'] ?? 0));
                    case 'product_group_get':
                        return $this->result($this->queryService->getGroup($args['group_id'] ?? 0));
                    case 'product_group_update':
                        return $this->result($this->groupUpdateService->update($args));
                    case 'product_group_tree':
                        return $this->result($this->queryService->groupTree(!empty($args['include_hidden'])));
                    case 'product_update':
                        return $this->result($this->updateService->update($args));
                    default:
                        return $this->error('Unknown tool: ' . $name);
                }
            } catch (\InvalidArgumentException $e) {
                return $this->error($e->getMessage());
            } catch (\RuntimeException $e) {
                return $this->error($e->getMessage());
            } catch (\Throwable $e) {
                return $this->error('Product tool execution failed');
            }
        });
    }

    private function tool($name, $description, $title, array $properties, array $required, $readOnly, array $outputSchema)
    {
        return new Tool(
            $name,
            new ToolInputSchema(ToolInputProperties::fromArray($properties), $required),
            $description,
            new ToolAnnotations($title, $readOnly, false, true, false),
            $title,
            null,
            $outputSchema
        );
    }

    private function arguments($params)
    {
        $args = $params->arguments ?? [];
        if (is_object($args)) {
            $args = json_decode(json_encode($args), true);
        }
        return is_array($args) ? $args : [];
    }

    private function result(array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new CallToolResult(
            [new TextContent($json === false ? '{"error":"result_encode_failed"}' : $json)],
            false,
            null,
            $data
        );
    }

    private function error($message)
    {
        return new CallToolResult([
            new TextContent(json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ], true);
    }
}
