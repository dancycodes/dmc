<?php

/**
 * F-087: Quarter List View -- Unit Tests
 *
 * Tests for the enhanced quarter list within expanded town sections on the locations page.
 * Verifies quarter display, sorting, group filter, empty state, edit/delete actions,
 * and translation strings.
 *
 * BR-242: Quarter list shows all quarters for the selected town
 * BR-243: Each entry displays: quarter name (current locale), delivery fee (XAF), group name (if assigned)
 * BR-244: Quarters are sorted alphabetically by name in the current locale
 * BR-245: Filter by group is available if quarter groups exist for this tenant
 * BR-246: Delivery fee of 0 is displayed as "Free delivery"
 * BR-247: Quarters in a group show the group's fee (not their individual fee) with group name indicated
 * BR-248: Empty state shown when no quarters exist for the town
 * BR-249: List updates via Gale when quarters change
 */

use App\Services\DeliveryAreaService;

$projectRoot = dirname(__DIR__, 3);

// ============================================================
// Test group: DeliveryAreaService — Quarter sorting and group data
// ============================================================
describe('DeliveryAreaService quarter list enhancements', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('sorts quarters alphabetically by locale name (BR-244)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        // Look for the sortBy call in getDeliveryAreasData
        expect($content)->toContain('->sortBy(');
        expect($content)->toContain('mb_strtolower');
    });

    it('includes group_id in quarter data (BR-243)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain("'group_id'");
    });

    it('includes group_name in quarter data (BR-243, BR-247)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain("'group_name'");
    });

    it('includes group_fee in quarter data (BR-247)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain("'group_fee'");
    });

    it('defaults group fields to null when no groups exist', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain("'group_id' => null");
        expect($methodContent)->toContain("'group_name' => null");
        expect($methodContent)->toContain("'group_fee' => null");
    });

    it('uses QuarterGroup Eloquent model for quarter groups (F-090 replaced Schema::hasTable)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain('QuarterGroup::query()');
    });

    it('has getQuarterGroupsForArea method (BR-245)', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getQuarterGroupsForArea');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('getQuarterGroupsForArea uses QuarterGroup Eloquent model (F-090 replaced Schema::hasTable)', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getQuarterGroupsForArea'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain('QuarterGroup::query()');
    });

    it('getQuarterGroupsForArea accepts Tenant and deliveryAreaId', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getQuarterGroupsForArea');
        $params = $reflection->getParameters();
        expect($params)->toHaveCount(2);
        expect($params[0]->getName())->toBe('tenant');
        expect($params[1]->getName())->toBe('deliveryAreaId');
    });

    it('getQuarterGroupsForArea returns typed array', function () {
        $reflection = new ReflectionMethod(DeliveryAreaService::class, 'getQuarterGroupsForArea');
        expect($reflection->getReturnType()->getName())->toBe('array');
    });
});

// ============================================================
// Test group: Blade View — Quarter List View enhancements (F-087)
// ============================================================
describe('Locations index blade view — quarter list enhancements', function () {
    $projectRoot = dirname(__DIR__, 3);
    $viewPath = $projectRoot.'/resources/views/cook/locations/index.blade.php';

    it('has F-087 feature reference in comment header', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('F-087: Quarter List View');
    });

    it('displays quarter name in current locale (BR-243)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$quarterName');
        expect($content)->toContain("quarter['quarter_name_fr']");
        expect($content)->toContain("quarter['quarter_name_en']");
    });

    it('shows delivery fee in XAF format (BR-243)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('number_format($effectiveFee)');
        expect($content)->toContain("__('XAF')");
    });

    it('shows Free delivery badge for 0 fee (BR-246)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Free delivery')");
        expect($content)->toContain('bg-success-subtle');
        expect($content)->toContain('text-success');
    });

    it('uses effective fee considering group override (BR-247)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("\$effectiveFee = \$quarter['group_fee'] !== null ? \$quarter['group_fee'] : \$quarter['delivery_fee']");
    });

    it('shows group badge for grouped quarters (BR-247)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$isGrouped');
        expect($content)->toContain("quarter['group_name']");
        expect($content)->toContain('bg-info-subtle');
        expect($content)->toContain('text-info');
    });

    it('has group filter dropdown (BR-245)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('All quarters')");
        expect($content)->toContain("__('Filter by group')");
        expect($content)->toContain('group-filter-');
        expect($content)->toContain('setGroupFilter(');
    });

    it('shows group filter only when groups exist (BR-245)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$hasGroups');
        expect($content)->toContain('@if($hasGroups)');
    });

    it('filters quarters by group using Alpine (BR-245)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('getGroupFilter(');
        expect($content)->toContain('x-show="getGroupFilter(');
    });

    it('has edit button for each quarter (F-088)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Edit quarter')");
        expect($content)->toContain('startEditQuarter(');
    });

    it('has delete button for each quarter (F-089 stub)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delete quarter')");
        expect($content)->toContain('confirmDeleteQuarter(');
    });

    it('has improved empty state with Add Quarter button (BR-248)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('No quarters added yet. Add your first quarter.')");
    });

    it('has quarter delete confirmation modal', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delete this quarter?')");
        expect($content)->toContain('confirmDeleteQuarterId');
        expect($content)->toContain('cancelDeleteQuarter()');
        expect($content)->toContain('executeDeleteQuarter()');
    });

    it('has Alpine state for quarter deletion', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteQuarterId: null');
        expect($content)->toContain('confirmDeleteQuarterName:');
    });

    it('has Alpine state for group filter', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('quarterGroupFilter: {}');
    });

    it('has confirmDeleteQuarter Alpine method', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('confirmDeleteQuarter(quarterId, quarterName)');
    });

    it('has cancelDeleteQuarter Alpine method', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('cancelDeleteQuarter()');
    });

    it('has executeDeleteQuarter Alpine method with DELETE', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('executeDeleteQuarter()');
        expect($content)->toContain("method: 'DELETE'");
    });

    it('has getGroupFilter Alpine method', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('getGroupFilter(areaId)');
    });

    it('has setGroupFilter Alpine method', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('setGroupFilter(areaId, groupId)');
    });

    it('quarter delete modal has accessibility attributes', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("__('Delete quarter confirmation')");
        expect($content)->toContain('aria-modal="true"');
    });

    it('quarter edit button triggers inline edit form (F-088)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // The edit button triggers inline edit via Alpine startEditQuarter method
        expect($content)->toContain('startEditQuarter(');
        expect($content)->toContain('editingQuarterId');
    });

    it('quarter delete action URL uses correct pattern', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("'/dashboard/locations/quarters/' + this.confirmDeleteQuarterId");
    });

    it('computes hasGroups from quarters data', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("collect(\$area['quarters'])->whereNotNull('group_id')->isNotEmpty()");
    });

    it('extracts unique groups from quarters data for filter options', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain("collect(\$area['quarters'])->whereNotNull('group_id')");
        expect($content)->toContain("->pluck('group_name', 'group_id')");
    });

    it('displays alternate locale quarter name when different (BR-243)', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        expect($content)->toContain('$altQuarterName');
        expect($content)->toContain("quarter['quarter_name_en'] !== \$quarter['quarter_name_fr']");
    });

    it('uses semantic color tokens for quarter list elements', function () use ($viewPath) {
        $content = file_get_contents($viewPath);
        // Quarter list section uses semantic tokens
        expect($content)->toContain('bg-surface-alt');
        expect($content)->toContain('text-on-surface-strong');
        expect($content)->toContain('bg-primary-subtle');
        expect($content)->toContain('text-primary');
        expect($content)->toContain('bg-success-subtle');
        expect($content)->toContain('text-success');
        expect($content)->toContain('bg-danger-subtle');
        expect($content)->toContain('text-danger');
    });
});

// ============================================================
// Test group: Translation Strings
// ============================================================
describe('Quarter list view translation strings', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('has English translations for quarter list view', function () use ($projectRoot) {
        $enContent = file_get_contents($projectRoot.'/lang/en.json');
        $en = json_decode($enContent, true);

        expect($en)->toHaveKey('All quarters');
        expect($en)->toHaveKey('Filter by group');
        expect($en)->toHaveKey('Edit quarter');
        expect($en)->toHaveKey('Delete quarter');
        expect($en)->toHaveKey('Delete this quarter?');
        expect($en)->toHaveKey('Delete quarter confirmation');
        expect($en)->toHaveKey('No quarters added yet. Add your first quarter.');
        expect($en)->toHaveKey('Free delivery');
    });

    it('has French translations for quarter list view', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr)->toHaveKey('All quarters');
        expect($fr)->toHaveKey('Filter by group');
        expect($fr)->toHaveKey('Edit quarter');
        expect($fr)->toHaveKey('Delete quarter');
        expect($fr)->toHaveKey('Delete this quarter?');
        expect($fr)->toHaveKey('Delete quarter confirmation');
        expect($fr)->toHaveKey('No quarters added yet. Add your first quarter.');
        expect($fr)->toHaveKey('Free delivery');
    });

    it('French translations are not identical to English keys', function () use ($projectRoot) {
        $frContent = file_get_contents($projectRoot.'/lang/fr.json');
        $fr = json_decode($frContent, true);

        expect($fr['All quarters'])->not->toBe('All quarters');
        expect($fr['Edit quarter'])->not->toBe('Edit quarter');
        expect($fr['Delete quarter'])->not->toBe('Delete quarter');
        expect($fr['Delete this quarter?'])->not->toBe('Delete this quarter?');
        expect($fr['No quarters added yet. Add your first quarter.'])->not->toBe('No quarters added yet. Add your first quarter.');
    });
});

// ============================================================
// Test group: BR-244 — Quarter alphabetical sorting
// ============================================================
describe('Quarter sorting in DeliveryAreaService', function () {
    $projectRoot = dirname(__DIR__, 3);

    it('sorts quarters by locale name using sortBy', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain('->sortBy(fn (DeliveryAreaQuarter $daq)');
    });

    it('uses mb_strtolower for case-insensitive sorting', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        // The sortBy uses mb_strtolower on the quarter name
        expect($methodContent)->toContain('mb_strtolower');
    });

    it('calls ->values() after sorting to reindex', function () use ($projectRoot) {
        $content = file_get_contents($projectRoot.'/app/Services/DeliveryAreaService.php');
        $methodContent = substr($content, strpos($content, 'public function getDeliveryAreasData'));
        $methodEnd = strpos($methodContent, "\n    /**", 10);
        $methodContent = substr($methodContent, 0, $methodEnd ?: strlen($methodContent));
        expect($methodContent)->toContain('->values()->all()');
    });
});
