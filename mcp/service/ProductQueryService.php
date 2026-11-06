<?php

namespace addons\mcp\service;

class ProductQueryService
{
    private $priceFields = [
        'osetupfee',
        'hsetupfee',
        'dsetupfee',
        'ontrialfee',
        'msetupfee',
        'qsetupfee',
        'ssetupfee',
        'asetupfee',
        'bsetupfee',
        'tsetupfee',
        'foursetupfee',
        'fivesetupfee',
        'sixsetupfee',
        'sevensetupfee',
        'eightsetupfee',
        'ninesetupfee',
        'tensetupfee',
        'onetime',
        'hour',
        'day',
        'ontrial',
        'monthly',
        'quarterly',
        'semiannually',
        'annually',
        'biennially',
        'triennially',
        'fourly',
        'fively',
        'sixly',
        'sevenly',
        'eightly',
        'ninely',
        'tenly',
    ];

    public function list(array $args)
    {
        $page = max(1, intval($args['page'] ?? 1));
        $pageSize = intval($args['page_size'] ?? 20);
        if ($pageSize < 1) {
            $pageSize = 20;
        }
        $pageSize = min(100, $pageSize);

        $query = \think\Db::name('products')
            ->alias('p')
            ->leftJoin('product_groups g', 'p.gid = g.id')
            ->leftJoin('product_first_groups fg', 'g.gid = fg.id');

        if (!empty($args['keyword'])) {
            $keyword = '%' . trim((string) $args['keyword']) . '%';
            $query->whereLike('p.name', $keyword);
        }
        if (isset($args['first_group_id']) && $args['first_group_id'] !== null && $args['first_group_id'] !== '') {
            $query->where('fg.id', intval($args['first_group_id']));
        }
        if (isset($args['group_id']) && $args['group_id'] !== null && $args['group_id'] !== '') {
            $query->where('p.gid', intval($args['group_id']));
        }
        if (!empty($args['type'])) {
            $query->where('p.type', (string) $args['type']);
        }
        foreach (['hidden', 'retired', 'stock_control'] as $flag) {
            if (isset($args[$flag]) && $args[$flag] !== null && $args[$flag] !== '') {
                $query->where('p.' . $flag, intval((bool) $args[$flag]));
            }
        }

        $total = (clone $query)->count();

        $sortMap = [
            'id' => 'p.id',
            'name' => 'p.name',
            'order' => 'p.order',
            'qty' => 'p.qty',
            'create_time' => 'p.create_time',
            'update_time' => 'p.update_time',
        ];
        $sortBy = $sortMap[(string) ($args['sort_by'] ?? 'order')] ?? 'p.order';
        $sortOrder = strtolower((string) ($args['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $rows = $query
            ->field('p.id,p.type,p.gid,p.name,p.description,p.hidden,p.retired,p.stock_control,p.qty,p.order,p.pay_method,p.auto_setup,p.zjmf_api_id,p.create_time,p.update_time,g.name as group_name,g.gid as first_group_id,fg.name as first_group_name')
            ->order($sortBy, $sortOrder)
            ->order('p.id', 'asc')
            ->page($page, $pageSize)
            ->select();

        if (!is_array($rows)) {
            $rows = $rows->toArray();
        }

        $rows = array_values($rows);
        $this->attachHostCounts($rows);
        foreach ($rows as &$row) {
            $row['create_time'] = $this->formatTime($row['create_time'] ?? 0);
            $row['update_time'] = $this->formatTime($row['update_time'] ?? 0);
        }
        unset($row);

        return [
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => intval($total),
                'total_pages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 0,
            ],
        ];
    }

    public function get($productId)
    {
        $productId = intval($productId);
        if ($productId <= 0) {
            throw new \InvalidArgumentException('product_id must be a positive integer');
        }

        $row = \think\Db::name('products')
            ->alias('p')
            ->leftJoin('product_groups g', 'p.gid = g.id')
            ->leftJoin('product_first_groups fg', 'g.gid = fg.id')
            ->field('p.*,g.name as group_name,g.gid as first_group_id,fg.name as first_group_name')
            ->where('p.id', $productId)
            ->find();

        if (empty($row)) {
            throw new \RuntimeException('product not found');
        }

        if (!is_array($row)) {
            $row = $row->toArray();
        }

        $payType = json_decode($row['pay_type'] ?? '', true);
        $row['pay_type'] = is_array($payType) ? $payType : [];
        $row['create_time'] = $this->formatTime($row['create_time'] ?? 0);
        $row['update_time'] = $this->formatTime($row['update_time'] ?? 0);

        $row['host'] = null;
        $row['password'] = null;
        foreach (range(1, 24) as $index) {
            unset($row['config_option' . $index]);
        }

        $counts = $this->hostCounts([$productId]);
        $row['host_count'] = $counts[$productId]['host_count'] ?? 0;
        $row['active_host_count'] = $counts[$productId]['active_host_count'] ?? 0;
        $row['pricing'] = $this->getPricing($productId);
        $row['config_group_ids'] = \think\Db::name('product_config_links')->where('pid', $productId)->column('gid');
        $row['upgrade_product_ids'] = \think\Db::name('product_upgrade_products')->where('product_id', $productId)->column('upgrade_product_id');
        $row['custom_field_count'] = intval(\think\Db::name('customfields')->where('type', 'product')->where('relid', $productId)->count());
        $row['download_count'] = intval(\think\Db::name('product_downloads')->where('product_id', $productId)->count());
        $row['product_shopping_url'] = request()->domain() . '/cart?action=configureproduct&pid=' . $productId;
        $row['product_group_url'] = request()->domain() . '/cart?gid=' . intval($row['gid']) . '&fid=' . intval($row['first_group_id']);

        return $row;
    }

    public function getGroup($groupId)
    {
        $groupId = intval($groupId);
        if ($groupId <= 0) {
            throw new \InvalidArgumentException('group_id must be a positive integer');
        }

        $group = \think\Db::name('product_groups')
            ->field('id,gid,name,headline,tagline,hidden,order,alias,tpl_type,is_upstream,zjfm_api_id,create_time,update_time')
            ->where('id', $groupId)
            ->find();
        if (empty($group)) {
            throw new \RuntimeException('product group not found');
        }
        if (!is_array($group)) {
            $group = $group->toArray();
        }

        $customFields = ['badge' => '', 'seo_desc' => ''];
        $rows = \think\Db::name('product_groups_customfields')
            ->field('name,value')
            ->where('relid', $groupId)
            ->whereIn('name', ['badge', 'seo_desc'])
            ->select();
        if (!is_array($rows)) {
            $rows = $rows->toArray();
        }
        foreach ($rows as $row) {
            if (array_key_exists($row['name'], $customFields)) {
                $customFields[$row['name']] = (string) $row['value'];
            }
        }

        $group['custom_fields'] = $customFields;
        $group['create_time'] = $this->formatTime($group['create_time'] ?? 0);
        $group['update_time'] = $this->formatTime($group['update_time'] ?? 0);

        return $group;
    }

    public function groupTree($includeHidden = false)
    {
        $firstQuery = \think\Db::name('product_first_groups')->order('order', 'asc')->order('id', 'asc');
        if (!$includeHidden) {
            $firstQuery->where('hidden', 0);
        }
        $firstGroups = $firstQuery->select();
        if (!is_array($firstGroups)) {
            $firstGroups = $firstGroups->toArray();
        }

        $groupQuery = \think\Db::name('product_groups')->order('order', 'asc')->order('id', 'asc');
        if (!$includeHidden) {
            $groupQuery->where('hidden', 0);
        }
        $groups = $groupQuery->select();
        if (!is_array($groups)) {
            $groups = $groups->toArray();
        }

        $productCounts = [];
        $countRows = \think\Db::name('products')
            ->alias('p')
            ->leftJoin('host h', 'h.productid = p.id')
            ->field('p.gid as gid,COUNT(h.id) as product_host_count,SUM(CASE WHEN h.domainstatus = "Active" THEN 1 ELSE 0 END) as active_host_count')
            ->group('p.gid')
            ->select();
        if (!is_array($countRows)) {
            $countRows = $countRows->toArray();
        }
        foreach ($countRows as $countRow) {
            $gid = intval($countRow['gid']);
            $productCounts[$gid] = [
                'product_host_count' => intval($countRow['product_host_count'] ?? 0),
                'active_host_count' => intval($countRow['active_host_count'] ?? 0),
            ];
        }

        $groupProducts = \think\Db::name('products')->field('gid,COUNT(*) as product_count')->group('gid')->select();
        if (!is_array($groupProducts)) {
            $groupProducts = $groupProducts->toArray();
        }
        foreach ($groupProducts as $countRow) {
            $gid = intval($countRow['gid']);
            if (!isset($productCounts[$gid])) {
                $productCounts[$gid] = ['product_host_count' => 0, 'active_host_count' => 0];
            }
            $productCounts[$gid]['product_count'] = intval($countRow['product_count'] ?? 0);
        }

        $groupsByFirst = [];
        foreach ($groups as $group) {
            $group['product_count'] = $productCounts[intval($group['id'])]['product_count'] ?? 0;
            $group['host_count'] = $productCounts[intval($group['id'])]['product_host_count'] ?? 0;
            $group['active_host_count'] = $productCounts[intval($group['id'])]['active_host_count'] ?? 0;
            $group['create_time'] = $this->formatTime($group['create_time'] ?? 0);
            $group['update_time'] = $this->formatTime($group['update_time'] ?? 0);
            $groupsByFirst[intval($group['gid'])][] = $group;
        }

        $tree = [];
        foreach ($firstGroups as $firstGroup) {
            $firstGroup['create_time'] = $this->formatTime($firstGroup['create_time'] ?? 0);
            $firstGroup['update_time'] = $this->formatTime($firstGroup['update_time'] ?? 0);
            $firstGroup['groups'] = $groupsByFirst[intval($firstGroup['id'])] ?? [];
            $tree[] = $firstGroup;
        }

        return ['tree' => $tree];
    }

    private function attachHostCounts(array &$rows)
    {
        $ids = array_values(array_filter(array_map('intval', array_column($rows, 'id'))));
        if (empty($ids)) {
            foreach ($rows as &$row) {
                $row['host_count'] = 0;
                $row['active_host_count'] = 0;
            }
            unset($row);
            return;
        }

        $counts = $this->hostCounts($ids);
        foreach ($rows as &$row) {
            $id = intval($row['id']);
            $row['host_count'] = $counts[$id]['host_count'] ?? 0;
            $row['active_host_count'] = $counts[$id]['active_host_count'] ?? 0;
        }
        unset($row);
    }

    private function hostCounts(array $ids)
    {
        $rows = \think\Db::name('host')
            ->field('productid,COUNT(*) as host_count,SUM(CASE WHEN domainstatus = "Active" THEN 1 ELSE 0 END) as active_host_count')
            ->whereIn('productid', $ids)
            ->group('productid')
            ->select();

        if (!is_array($rows)) {
            $rows = $rows->toArray();
        }

        $result = [];
        foreach ($rows as $row) {
            $result[intval($row['productid'])] = [
                'host_count' => intval($row['host_count'] ?? 0),
                'active_host_count' => intval($row['active_host_count'] ?? 0),
            ];
        }
        return $result;
    }

    private function getPricing($productId)
    {
        $currencies = \think\Db::name('currencies')->field('id,code,default')->select();
        if (!is_array($currencies)) {
            $currencies = $currencies->toArray();
        }
        $currencyMap = [];
        foreach ($currencies as $currency) {
            $currencyMap[intval($currency['id'])] = $currency;
        }

        $rows = \think\Db::name('pricing')->where('type', 'product')->where('relid', $productId)->select();
        if (!is_array($rows)) {
            $rows = $rows->toArray();
        }

        $priceTypes = config('price_type') ?: [];
        $result = [];
        foreach ($rows as $row) {
            $currencyId = intval($row['currency']);
            $cycles = [];
            foreach ($priceTypes as $cycle => $fields) {
                $priceField = $fields[0] ?? $cycle;
                $setupField = $fields[1] ?? '';
                $priceValue = $row[$priceField] ?? null;
                $setupValue = $setupField !== '' ? ($row[$setupField] ?? null) : null;
                $cycles[(string) $cycle] = [
                    'enabled' => $priceValue !== null && floatval($priceValue) >= 0,
                    'price' => $this->formatDecimal($priceValue),
                    'setup_fee' => $this->formatDecimal($setupValue),
                ];
            }
            $result[] = [
                'currency_id' => $currencyId,
                'currency_code' => $currencyMap[$currencyId]['code'] ?? '',
                'is_default' => !empty($currencyMap[$currencyId]['default']),
                'cycles' => $cycles,
            ];
        }
        return $result;
    }

    private function formatTime($value)
    {
        $value = intval($value);
        return $value > 0 ? date('c', $value) : null;
    }

    private function formatDecimal($value)
    {
        return $value === null ? null : number_format(floatval($value), 2, '.', '');
    }
}
