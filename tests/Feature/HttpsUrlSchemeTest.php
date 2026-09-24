<?php

use App\Providers\AppServiceProvider;

test('generated urls use https when the app url is https', function () {
    config(['app.url' => 'https://tickets.example.com']);

    (new AppServiceProvider($this->app))->boot();

    expect(asset('build/assets/app.js'))->toStartWith('https://')
        ->and(url('/dashboard'))->toStartWith('https://');
});

test('generated urls keep the request scheme when the app url is http', function () {
    config(['app.url' => 'http://localhost:8000']);

    (new AppServiceProvider($this->app))->boot();

    expect(asset('build/assets/app.js'))->toStartWith('http://');
});
