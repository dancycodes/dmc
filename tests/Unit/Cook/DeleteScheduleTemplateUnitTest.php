<?php

/**
 * F-104: Delete Schedule Template -- Unit Tests
 *
 * Tests for ScheduleTemplateController destroy method,
 * ScheduleTemplateService deleteTemplate, blade view delete modal,
 * route configuration, and translation strings.
 *
 * BR-146: Deleting a template does NOT affect day schedules (values were copied)
 * BR-147: Confirmation dialog shown before deletion
 * BR-148: Confirmation includes applied-to count when > 0
 * BR-149: Hard delete (not soft delete)
 * BR-150: Template deletion logged via Spatie Activitylog
 * BR-151: Only users with can-manage-schedules permission can delete
 * BR-152: template_id on CookSchedule entries set to null after deletion
 */

use App\Http\Controllers\Cook\ScheduleTemplateController;
use App\Models\CookSchedule;
use App\Models\ScheduleTemplate;
use App\Models\Tenant;
use App\Services\ScheduleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

$projectRoot = dirname(__DIR__, 3);

uses(Tests\TestCase::class, RefreshDatabase::class);

// ============================================================
// Test group: ScheduleTemplateController destroy method
// ============================================================
describe('ScheduleTemplateController destroy', function () {
    it('has a destroy method', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'destroy');
        expect($reflection->isPublic())->toBeTrue();
    });

    it('destroy method accepts ScheduleTemplate parameter', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'destroy');
        $params = $reflection->getParameters();

        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        expect($paramNames)->toContain('scheduleTemplate');
    });

    it('destroy method accepts Request parameter', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'destroy');
        $params = $reflection->getParameters();

        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        expect($paramNames)->toContain('request');
    });

    it('destroy method accepts ScheduleTemplateService parameter', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'destroy');
        $params = $reflection->getParameters();

        $paramNames = array_map(fn ($p) => $p->getName(), $params);
        expect($paramNames)->toContain('templateService');
    });

    it('destroy method has mixed return type', function () {
        $reflection = new ReflectionMethod(ScheduleTemplateController::class, 'destroy');
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType->getName())->toBe('mixed');
    });
});

// ============================================================
// Test group: ScheduleTemplateService deleteTemplate
// ============================================================
describe('ScheduleTemplateService deleteTemplate', function () {
    it('deletes a template successfully', function () {
        $template = ScheduleTemplate::factory()->create();
        $templateId = $template->id;
        $service = app(ScheduleTemplateService::class);

        $result = $service->deleteTemplate($template);

        expect($result['success'])->toBeTrue();
        expect($result['template_name'])->toBe($template->name);
        expect(ScheduleTemplate::find($templateId))->toBeNull();
    });

    it('returns the applied count in the result', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

        // Create cook schedules referencing this template
        CookSchedule::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        $service = app(ScheduleTemplateService::class);
        $result = $service->deleteTemplate($template);

        expect($result['success'])->toBeTrue();
        expect($result['applied_count'])->toBe(3);
    });

    it('returns zero applied count for unused template', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->deleteTemplate($template);

        expect($result['success'])->toBeTrue();
        expect($result['applied_count'])->toBe(0);
    });

    it('hard deletes the template (BR-149)', function () {
        $template = ScheduleTemplate::factory()->create();
        $templateId = $template->id;
        $service = app(ScheduleTemplateService::class);

        $service->deleteTemplate($template);

        // Verify not in database at all (hard delete, not soft)
        $this->assertDatabaseMissing('schedule_templates', ['id' => $templateId]);
    });

    it('nullifies template_id on affected cook schedules (BR-152)', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

        $schedule1 = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);
        $schedule2 = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        $service = app(ScheduleTemplateService::class);
        $service->deleteTemplate($template);

        $schedule1->refresh();
        $schedule2->refresh();

        expect($schedule1->template_id)->toBeNull();
        expect($schedule2->template_id)->toBeNull();
    });

    it('does not affect unrelated cook schedules', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);
        $otherTemplate = ScheduleTemplate::factory()->withName('Other Template')->create(['tenant_id' => $tenant->id]);

        $relatedSchedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);
        $unrelatedSchedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $otherTemplate->id,
        ]);

        $service = app(ScheduleTemplateService::class);
        $service->deleteTemplate($template);

        $relatedSchedule->refresh();
        $unrelatedSchedule->refresh();

        expect($relatedSchedule->template_id)->toBeNull();
        expect($unrelatedSchedule->template_id)->toBe($otherTemplate->id);
    });

    it('preserves day schedule values after template deletion (BR-146)', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        $schedule = CookSchedule::factory()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'order_start_time' => '18:00',
            'order_start_day_offset' => 1,
            'order_end_time' => '08:00',
            'order_end_day_offset' => 0,
        ]);

        $service = app(ScheduleTemplateService::class);
        $service->deleteTemplate($template);

        $schedule->refresh();

        // Values should be preserved — only template_id is nullified
        expect($schedule->order_start_time)->toContain('18:00');
        expect($schedule->order_start_day_offset)->toBe(1);
        expect($schedule->order_end_time)->toContain('08:00');
        expect($schedule->order_end_day_offset)->toBe(0);
        expect($schedule->template_id)->toBeNull();
    });

    it('returns template name in the result', function () {
        $template = ScheduleTemplate::factory()->withName('Dinner Service')->create();
        $service = app(ScheduleTemplateService::class);

        $result = $service->deleteTemplate($template);

        expect($result['template_name'])->toBe('Dinner Service');
    });
});

// ============================================================
// Test group: ScheduleTemplateService getAppliedCount
// ============================================================
describe('ScheduleTemplateService getAppliedCount', function () {
    it('returns zero for template with no applied schedules', function () {
        $template = ScheduleTemplate::factory()->create();
        $service = app(ScheduleTemplateService::class);

        expect($service->getAppliedCount($template))->toBe(0);
    });

    it('returns correct count for template with applied schedules', function () {
        $tenant = Tenant::factory()->create();
        $template = ScheduleTemplate::factory()->create(['tenant_id' => $tenant->id]);

        CookSchedule::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
        ]);

        $service = app(ScheduleTemplateService::class);

        expect($service->getAppliedCount($template))->toBe(5);
    });
});

// ============================================================
// Test group: Route configuration
// ============================================================
describe('Route configuration', function () {
    it('has destroy route defined', function () {
        $route = route('cook.schedule-templates.destroy', ['scheduleTemplate' => 1]);

        expect($route)->toContain('/dashboard/schedule/templates/1');
    });

    it('destroy route uses DELETE method', function () {
        $routes = app('router')->getRoutes();
        $route = $routes->getByName('cook.schedule-templates.destroy');

        expect($route)->not->toBeNull();
        expect($route->methods())->toContain('DELETE');
    });

    it('destroy route points to correct controller method', function () {
        $routes = app('router')->getRoutes();
        $route = $routes->getByName('cook.schedule-templates.destroy');

        expect($route->getActionName())->toContain('ScheduleTemplateController@destroy');
    });
});

// ============================================================
// Test group: View file — delete modal
// ============================================================
describe('Index view delete modal', function () {
    it('exists at the expected path', function () {
        $root = dirname(__DIR__, 3);
        expect(file_exists($root.'/resources/views/cook/schedule/templates/index.blade.php'))->toBeTrue();
    });

    it('extends cook dashboard layout', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain("@extends('layouts.cook-dashboard')");
    });

    it('contains Alpine.js confirmation modal state', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('showDeleteModal');
        expect($content)->toContain('deleteTemplateId');
        expect($content)->toContain('deleteTemplateName');
        expect($content)->toContain('deleteTemplateAppliedCount');
    });

    it('has confirmDelete method', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('confirmDelete(');
    });

    it('has cancelDelete method', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('cancelDelete()');
    });

    it('has executeDelete method with $action DELETE', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('executeDelete()');
        expect($content)->toContain("method: 'DELETE'");
    });

    it('uses button elements for delete triggers (not anchor links)', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        // Desktop delete action should be button, not anchor
        expect($content)->toContain('x-on:click="confirmDelete(');
    });

    it('shows applied-to count in confirmation dialog (BR-148)', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('deleteTemplateAppliedCount > 0');
        expect($content)->toContain("__('This template was applied to')");
        expect($content)->toContain("__('day schedule(s). Those schedules will keep their current settings.')");
    });

    it('has delete confirmation title', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain("__('Delete Template')");
    });

    it('has cancel and delete buttons in modal', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain("__('Cancel')");
        expect($content)->toContain("__('Delete')");
    });

    it('uses danger color tokens for destructive UI elements', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('bg-danger');
        expect($content)->toContain('text-danger');
        expect($content)->toContain('bg-danger-subtle');
    });

    it('has accessible modal with role=dialog and aria-modal', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('role="dialog"');
        expect($content)->toContain('aria-modal="true"');
    });

    it('closes modal on escape key', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('keydown.escape.window');
        expect($content)->toContain('cancelDelete()');
    });

    it('closes modal on backdrop click', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('x-on:click="cancelDelete()"');
    });

    it('uses __() for all user-facing strings', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain("__('Delete Template')");
        expect($content)->toContain("__('Delete template')");
        expect($content)->toContain("__('This cannot be undone.')");
        expect($content)->toContain("__('Cancel')");
    });

    it('passes template data to confirmDelete in both desktop and mobile views', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        // Both desktop and mobile should have confirmDelete buttons
        preg_match_all('/confirmDelete\(/', $content, $matches);
        // At least 2 occurrences: one in desktop table + one in mobile cards (per template, but the template is the same)
        expect(count($matches[0]))->toBeGreaterThanOrEqual(2);
    });

    it('uses addslashes for template name in confirmDelete', function () {
        $root = dirname(__DIR__, 3);
        $content = file_get_contents($root.'/resources/views/cook/schedule/templates/index.blade.php');

        expect($content)->toContain('addslashes($template->name)');
    });
});

// ============================================================
// Test group: Translation strings
// ============================================================
describe('Translation strings', function () {
    it('has required English translations', function () {
        $root = dirname(__DIR__, 3);
        $translations = json_decode(file_get_contents($root.'/lang/en.json'), true);

        expect($translations)->toHaveKey('Delete Template');
        expect($translations)->toHaveKey('Delete template');
        expect($translations)->toHaveKey('Schedule template ":name" deleted successfully.');
        expect($translations)->toHaveKey('This template was applied to');
        expect($translations)->toHaveKey('day schedule(s). Those schedules will keep their current settings.');
    });

    it('has required French translations', function () {
        $root = dirname(__DIR__, 3);
        $translations = json_decode(file_get_contents($root.'/lang/fr.json'), true);

        expect($translations)->toHaveKey('Delete Template');
        expect($translations)->toHaveKey('Delete template');
        expect($translations)->toHaveKey('Schedule template ":name" deleted successfully.');
        expect($translations)->toHaveKey('This template was applied to');
        expect($translations)->toHaveKey('day schedule(s). Those schedules will keep their current settings.');
    });
});
