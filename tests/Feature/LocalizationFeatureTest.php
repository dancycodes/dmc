<?php

use App\Models\User;
use App\Traits\HasTranslatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

// --- SetLocale Middleware Tests ---

test('SetLocale middleware sets locale from authenticated user preferred_language', function () {
    $user = User::factory()->withLanguage('fr')->create();

    $this->actingAs($user)->get('/');

    expect(App::getLocale())->toBe('fr');
});

test('SetLocale middleware uses English for authenticated user with en preference', function () {
    $user = User::factory()->withLanguage('en')->create();

    $this->actingAs($user)->get('/');

    expect(App::getLocale())->toBe('en');
});

test('SetLocale middleware user preference overrides browser Accept-Language', function () {
    $user = User::factory()->withLanguage('en')->create();

    $this->actingAs($user)
        ->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8'])
        ->get('/');

    expect(App::getLocale())->toBe('en');
});

test('SetLocale middleware sets locale from session for unauthenticated user', function () {
    $this->withSession(['locale' => 'fr'])->get('/');

    expect(App::getLocale())->toBe('fr');
});

test('SetLocale middleware session locale overrides browser Accept-Language', function () {
    $this->withSession(['locale' => 'fr'])
        ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
        ->get('/');

    expect(App::getLocale())->toBe('fr');
});

test('SetLocale middleware detects French from Accept-Language header', function () {
    $this->withHeaders(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8'])
        ->get('/');

    expect(App::getLocale())->toBe('fr');
});

test('SetLocale middleware detects English from Accept-Language header', function () {
    $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9'])
        ->get('/');

    expect(App::getLocale())->toBe('en');
});

test('SetLocale middleware falls back to English for unsupported locale', function () {
    $this->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
        ->get('/');

    expect(App::getLocale())->toBe('en');
});

test('SetLocale middleware falls back to English when Accept-Language is missing', function () {
    $this->get('/');

    expect(App::getLocale())->toBe('en');
});

test('locale priority: user > session > browser > default', function () {
    $user = User::factory()->withLanguage('fr')->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'en'])
        ->withHeaders(['Accept-Language' => 'en-US'])
        ->get('/');

    expect(App::getLocale())->toBe('fr');
});

// --- Translation Tests ---

test('translation helper returns French text when locale is fr', function () {
    App::setLocale('fr');

    expect(__('Login'))->toBe('Connexion');
    expect(__('Register'))->toBe('Inscription');
    expect(__('Save'))->toBe('Enregistrer');
});

test('translation helper returns English text when locale is en', function () {
    App::setLocale('en');

    expect(__('Login'))->toBe('Login');
    expect(__('Register'))->toBe('Register');
});

test('missing translation falls back to English key', function () {
    App::setLocale('fr');

    expect(__('This string has no French translation'))->toBe('This string has no French translation');
});

test('translation with parameters works in both locales', function () {
    App::setLocale('en');
    expect(__('Page :current of :total', ['current' => 1, 'total' => 5]))
        ->toBe('Page 1 of 5');

    App::setLocale('fr');
    expect(__('Page :current of :total', ['current' => 1, 'total' => 5]))
        ->toBe('Page 1 sur 5');
});

test('French validation messages are loaded correctly', function () {
    App::setLocale('fr');

    expect(__('validation.required', ['attribute' => 'nom']))
        ->toBe('Le champ nom est obligatoire.');
});

test('French auth messages are loaded correctly', function () {
    App::setLocale('fr');

    expect(__('auth.failed'))
        ->toBe('Ces identifiants ne correspondent pas à nos enregistrements.');
});

// --- Translation File Tests ---

test('en.json translation file exists with expected keys', function () {
    $enPath = lang_path('en.json');

    expect(file_exists($enPath))->toBeTrue();

    $translations = json_decode(file_get_contents($enPath), true);

    expect($translations)
        ->toBeArray()
        ->toHaveKey('Login')
        ->toHaveKey('Register')
        ->toHaveKey('Logout')
        ->toHaveKey('Email')
        ->toHaveKey('Password')
        ->toHaveKey('Save')
        ->toHaveKey('Cancel')
        ->toHaveKey('Dashboard');
});

test('fr.json translation file exists with same keys as en.json', function () {
    $enTranslations = json_decode(file_get_contents(lang_path('en.json')), true);
    $frTranslations = json_decode(file_get_contents(lang_path('fr.json')), true);

    $enKeys = array_keys($enTranslations);
    $frKeys = array_keys($frTranslations);

    expect($frKeys)->toBe($enKeys);
});

test('fr.json translations differ from en.json for key terms', function () {
    $enTranslations = json_decode(file_get_contents(lang_path('en.json')), true);
    $frTranslations = json_decode(file_get_contents(lang_path('fr.json')), true);

    expect($frTranslations['Login'])->not->toBe($enTranslations['Login']);
    expect($frTranslations['Register'])->not->toBe($enTranslations['Register']);
    expect($frTranslations['Save'])->not->toBe($enTranslations['Save']);
});

test('French validation translation file exists with required keys', function () {
    expect(file_exists(lang_path('fr/validation.php')))->toBeTrue();

    $validation = require lang_path('fr/validation.php');

    expect($validation)
        ->toBeArray()
        ->toHaveKey('required')
        ->toHaveKey('email')
        ->toHaveKey('confirmed');
});

test('French auth translation file exists with required keys', function () {
    expect(file_exists(lang_path('fr/auth.php')))->toBeTrue();

    $auth = require lang_path('fr/auth.php');

    expect($auth)
        ->toBeArray()
        ->toHaveKey('failed')
        ->toHaveKey('password')
        ->toHaveKey('throttle');
});

test('French passwords translation file exists with required keys', function () {
    expect(file_exists(lang_path('fr/passwords.php')))->toBeTrue();

    $passwords = require lang_path('fr/passwords.php');

    expect($passwords)
        ->toBeArray()
        ->toHaveKey('reset')
        ->toHaveKey('sent')
        ->toHaveKey('token')
        ->toHaveKey('user');
});

// --- Config Tests ---

test('app config includes available_locales with en and fr', function () {
    expect(config('app.available_locales'))
        ->toBeArray()
        ->toContain('en')
        ->toContain('fr');
});

test('app locale defaults to English', function () {
    expect(config('app.locale'))->toBe('en');
});

test('app fallback locale is English', function () {
    expect(config('app.fallback_locale'))->toBe('en');
});

// --- Helper Function Tests ---

test('localized helper returns column with current locale suffix', function () {
    App::setLocale('en');
    expect(localized('name'))->toBe('name_en');

    App::setLocale('fr');
    expect(localized('name'))->toBe('name_fr');
});

test('availableLocales helper returns configured locales', function () {
    expect(availableLocales())->toBe(['en', 'fr']);
});

// --- HasTranslatable Trait Tests (with app context) ---

test('HasTranslatable resolves to French column when locale is fr', function () {
    App::setLocale('fr');

    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name', 'description'];

        protected $guarded = [];
    };

    $model->forceFill([
        'name_en' => 'English Name',
        'name_fr' => 'Nom Français',
    ]);

    expect($model->name)->toBe('Nom Français');
});

test('HasTranslatable falls back to English when French value is empty', function () {
    App::setLocale('fr');

    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name'];

        protected $guarded = [];
    };

    $model->forceFill([
        'name_en' => 'English Fallback',
        'name_fr' => '',
    ]);

    expect($model->name)->toBe('English Fallback');
});

test('HasTranslatable falls back to English when French value is null', function () {
    App::setLocale('fr');

    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name'];

        protected $guarded = [];
    };

    $model->forceFill([
        'name_en' => 'English Default',
        'name_fr' => null,
    ]);

    expect($model->name)->toBe('English Default');
});

test('HasTranslatable returns English value when locale is en', function () {
    App::setLocale('en');

    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name'];

        protected $guarded = [];
    };

    $model->forceFill([
        'name_en' => 'English Value',
        'name_fr' => 'Valeur Française',
    ]);

    expect($model->name)->toBe('English Value');
});

test('HasTranslatable getTranslation uses current locale by default', function () {
    App::setLocale('fr');

    $model = new class extends Model
    {
        use HasTranslatable;

        protected array $translatable = ['name'];

        protected $guarded = [];
    };

    $model->forceFill([
        'name_fr' => 'Nom Français',
    ]);

    expect($model->getTranslation('name'))->toBe('Nom Français');
});

// --- User Model Tests ---

test('user preferred_language defaults to en in database', function () {
    $user = User::factory()->create();

    expect($user->preferred_language)->toBe('en');
});

test('user preferred_language can be set to fr', function () {
    $user = User::factory()->withLanguage('fr')->create();

    expect($user->fresh()->preferred_language)->toBe('fr');
});
