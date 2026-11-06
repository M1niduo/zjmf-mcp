<?php

namespace addons\mcp\service;

class ProductUpdateService
{
    public function update(array $args)
    {
        $productId = intval($args['product_id'] ?? 0);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('product_id must be a positive integer');
        }

        $product = \think\Db::name('products')
            ->field('id,name,description,order,update_time')
            ->where('id', $productId)
            ->find();
        if (empty($product)) {
            throw new \RuntimeException('product not found');
        }
        if (!is_array($product)) {
            $product = $product->toArray();
        }

        if (isset($args['expected_update_time']) && $args['expected_update_time'] !== null && intval($args['expected_update_time']) !== intval($product['update_time'])) {
            throw new \RuntimeException('product was modified by another request');
        }

        $changes = [];
        if (array_key_exists('name', $args)) {
            $name = trim((string) $args['name']);
            if ($name === '' || $this->length($name) > 100) {
                throw new \InvalidArgumentException('name must be between 1 and 100 characters');
            }
            if ($name !== $product['name']) {
                $changes['name'] = $name;
            }
        }
        if (array_key_exists('description', $args)) {
            $description = (string) $args['description'];
            if ($description !== $product['description']) {
                $changes['description'] = $description;
            }
        }
        if (array_key_exists('order', $args)) {
            if (!is_numeric($args['order'])) {
                throw new \InvalidArgumentException('order must be an integer');
            }
            $order = intval($args['order']);
            if ($order !== intval($product['order'])) {
                $changes['order'] = $order;
            }
        }

        if (empty($changes)) {
            return [
                'updated' => false,
                'dry_run' => !empty($args['dry_run']),
                'changed_fields' => [],
                'product_id' => $productId,
            ];
        }

        if (!empty($args['dry_run'])) {
            return [
                'updated' => false,
                'dry_run' => true,
                'changed_fields' => array_keys($changes),
                'changes' => $changes,
                'product_id' => $productId,
            ];
        }

        $changedFields = array_keys($changes);
        $changes['update_time'] = time();
        \think\Db::startTrans();
        try {
            \think\Db::name('products')->where('id', $productId)->update($changes);
            \think\Db::commit();
        } catch (\Throwable $e) {
            \think\Db::rollback();
            throw new \RuntimeException('product update failed');
        }

        $description = [];
        foreach ($changes as $field => $value) {
            if ($field !== 'update_time') {
                $description[] = $field;
            }
        }
        active_log(sprintf('MCP update product #%d: %s', $productId, implode(',', $description)));

        $warnings = [];
        try {
            hook('product_edit', ['pid' => $productId]);
        } catch (\Throwable $e) {
            $warnings[] = 'product_edit hook failed';
        }
        try {
            (new \app\common\logic\Product())->updateCache([$productId]);
        } catch (\Throwable $e) {
            $warnings[] = 'product cache refresh failed';
        }

        return [
            'updated' => true,
            'dry_run' => false,
            'product_id' => $productId,
            'changed_fields' => $changedFields,
            'warnings' => $warnings,
        ];
    }

    private function length($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
