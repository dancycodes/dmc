<?php

use App\Console\Commands\CreateSuperAdmin;

$projectRoot = dirname(__DIR__, 2);

it('is registered as dancymeals:create-super-admin', function () {
    $command = new CreateSuperAdmin;

    expect($command->getName())->toBe('dancymeals:create-super-admin');
});

it('has --force option in signature', function () {
    $command = new CreateSuperAdmin;
    $definition = $command->getDefinition();

    expect($definition->hasOption('force'))->toBeTrue()
        ->and($definition->getOption('force')->getDescription())
        ->toContain('additional super-admins');
});

it('has a meaningful description', function () {
    $command = new CreateSuperAdmin;

    expect($command->getDescription())
        ->toBe('Create the first super-admin user for the DancyMeals platform');
});

it('force option defaults to false', function () {
    $command = new CreateSuperAdmin;
    $definition = $command->getDefinition();

    expect($definition->getOption('force')->getDefault())->toBeFalse();
});

it('command class exists in correct namespace', function () {
    expect(class_exists(CreateSuperAdmin::class))->toBeTrue();
});

it('extends Illuminate Console Command', function () {
    $command = new CreateSuperAdmin;

    expect($command)->toBeInstanceOf(\Illuminate\Console\Command::class);
});

it('has handle method with int return type', function () {
    $reflection = new ReflectionMethod(CreateSuperAdmin::class, 'handle');

    expect($reflection->getReturnType()->getName())->toBe('int');
});

it('has private promptForEmail method', function () {
    $reflection = new ReflectionMethod(CreateSuperAdmin::class, 'promptForEmail');

    expect($reflection->isPrivate())->toBeTrue();
});

it('has private promptForPhone method', function () {
    $reflection = new ReflectionMethod(CreateSuperAdmin::class, 'promptForPhone');

    expect($reflection->isPrivate())->toBeTrue();
});

it('has private promptForPassword method', function () {
    $reflection = new ReflectionMethod(CreateSuperAdmin::class, 'promptForPassword');

    expect($reflection->isPrivate())->toBeTrue();
});
