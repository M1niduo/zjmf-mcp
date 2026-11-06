<?php

namespace addons\mcp\service;

class ProductGroupUpdateService
{
    private $customFieldNames = ['badge', 'seo_desc'];

    public function update(array $args)
    {
        $groupId = intval($args['group_id'] ?? 0);
        if ($groupId <= 0) {
            throw new \InvalidArgumentException('group_id must be a positive integer');
        }

        $group = \think\Db::name('product_groups')
            ->field('id,gid,name,headline,tagline,update_time')
            ->where('id', $groupId)
            ->find();
        if (empty($group)) {
            throw new \RuntimeException('product group not found');
        }
        if (!is_array($group)) {
            $group = $group->toArray();
        }

        if (isset($args['expected_update_time']) && $args['expected_update_time'] !== null && intval($args['expected_update_time']) !== intval($group['update_time'])) {
            throw new \RuntimeException('product group was modified by another request');
        }

        $changes = [];
        foreach (['name', 'headline', 'tagline'] as $field) {
            if (!array_key_exists($field, $args)) {
                continue;
            }
            $value = trim((string) $args[$field]);
            if ($this->length($value) > 255) {
                throw new \InvalidArgumentException($field . ' must not exceed 255 characters');
            }
            if ($field === 'name' && $value === '') {
                throw new \InvalidArgumentException('name must not be empty');
            }
            if ($value !== $group[$field]) {
                $changes[$field] = $value;
            }
        }

        $customFields = [];
        if (array_key_exists('custom_fields', $args)) {
            if (!is_array($args['custom_fields'])) {
                throw new \InvalidArgumentException('custom_fields must be an object');
            }
            foreach ($args['custom_fields'] as $name => $value) {
                if (!in_array($name, $this->customFieldNames, true)) {
                    throw new \InvalidArgumentException('unsupported custom field: ' . $name);
                }
                if (!is_scalar($value) && $value !== null) {
                    throw new \InvalidArgumentException('custom field value must be a string');
                }
                $value = (string) $value;
                if ($this->length($value) > 255) {
                    throw new \InvalidArgumentException('custom field value must not exceed 255 characters');
                }
                $customFields[$name] = $value;
            }
        }

        $oldCustomFields = $this->getCustomFields($groupId);
        $customChanges = [];
        foreach ($customFields as $name => $value) {
            if (!array_key_exists($name, $oldCustomFields) || $oldCustomFields[$name] !== $value) {
                $customChanges[$name] = $value;
            }
        }

        if (empty($changes) && empty($customChanges)) {
            return [
                'updated' => false,
                'dry_run' => !empty($args['dry_run']),
                'group_id' => $groupId,
                'changed_fields' => [],
                'changed_custom_fields' => [],
            ];
        }

        if (!empty($args['dry_run'])) {
            return [
                'updated' => false,
                'dry_run' => true,
                'group_id' => $groupId,
                'changed_fields' => array_keys($changes),
                'changed_custom_fields' => array_keys($customChanges),
                'changes' => $changes,
                'custom_field_changes' => $customChanges,
            ];
        }

        $changedFields = array_keys($changes);
        \think\Db::startTrans();
        try {
            if (!empty($changes)) {
                $changes['update_time'] = time();
                \think\Db::name('product_groups')->where('id', $groupId)->update($changes);
            }
            foreach ($customChanges as $name => $value) {
                $exists = \think\Db::name('product_groups_customfields')
                    ->where('relid', $groupId)
                    ->where('name', $name)
                    ->find();
                if ($exists) {
                    \think\Db::name('product_groups_customfields')
                        ->where('id', $exists['id'])
                        ->update(['value' => $value, 'update_time' => time()]);
                } else {
                    \think\Db::name('product_groups_customfields')->insert([
                        'relid' => $groupId,
                        'name' => $name,
                        'value' => $value,
                        'create_time' => time(),
                        'update_time' => time(),
                    ]);
                }
            }
            \think\Db::commit();
        } catch (\Throwable $e) {
            \think\Db::rollback();
            throw new \RuntimeException('product group update failed');
        }

        $logFields = array_merge($changedFields, array_map(function ($name) {
            return 'custom_fields.' . $name;
        }, array_keys($customChanges)));
        active_log(sprintf('MCP update product group #%d: %s', $groupId, implode(',', $logFields)));

        $warnings = [];
        $productIds = \think\Db::name('products')->where('gid', $groupId)->column('id');
        if (!empty($productIds)) {
            try {
                $productLogic = new \app\common\logic\Product();
                $productLogic->updateInfoCache();
                $productLogic->updateListCache($productIds);
            } catch (\Throwable $e) {
                $warnings[] = 'product cache refresh failed';
            }
        }

        return [
            'updated' => true,
            'dry_run' => false,
            'group_id' => $groupId,
            'changed_fields' => $changedFields,
            'changed_custom_fields' => array_keys($customChanges),
            'warnings' => $warnings,
        ];
    }

    private function getCustomFields($groupId)
    {
        $rows = \think\Db::name('product_groups_customfields')
            ->field('name,value')
            ->where('relid', $groupId)
            ->select();
        if (!is_array($rows)) {
            $rows = $rows->toArray();
        }
        $result = [];
        foreach ($rows as $row) {
            if (in_array($row['name'], $this->customFieldNames, true)) {
                $result[$row['name']] = (string) $row['value'];
            }
        }
        return $result;
    }

    private function length($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
