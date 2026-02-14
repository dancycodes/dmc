<?php

use App\Traits\HasTranslatable;
use Illuminate\Database\Eloquent\Model;

// --- HasTranslatable Trait Tests (no app context needed) ---

test('HasTranslatable does not intercept non-translatable attributes', function () {
    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name'];

        protected $guarded = [];
    };

    $model->forceFill([
        'slug' => 'test-slug',
    ]);

    expect($model->slug)->toBe('test-slug');
});

test('HasTranslatable getTranslatableAttributes returns declared attributes', function () {
    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name', 'description'];
    };

    expect($model->getTranslatableAttributes())->toBe(['name', 'description']);
});

test('HasTranslatable getTranslatableAttributes returns empty when not declared', function () {
    $model = new class extends Model
    {
        use HasTranslatable;
    };

    expect($model->getTranslatableAttributes())->toBe([]);
});

test('HasTranslatable setTranslation sets value for specific locale', function () {
    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name'];

        protected $guarded = [];
    };

    $model->setTranslation('name', 'fr', 'Nom Test');
    $model->setTranslation('name', 'en', 'Test Name');

    expect($model->getTranslation('name', 'fr'))->toBe('Nom Test');
    expect($model->getTranslation('name', 'en'))->toBe('Test Name');
});

test('HasTranslatable isTranslatableAttribute checks translatable array', function () {
    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name', 'description'];

        /**
         * Expose the protected method for testing.
         */
        public function testIsTranslatable(string $key): bool
        {
            return $this->isTranslatableAttribute($key);
        }
    };

    expect($model->testIsTranslatable('name'))->toBeTrue();
    expect($model->testIsTranslatable('description'))->toBeTrue();
    expect($model->testIsTranslatable('slug'))->toBeFalse();
    expect($model->testIsTranslatable('id'))->toBeFalse();
});
