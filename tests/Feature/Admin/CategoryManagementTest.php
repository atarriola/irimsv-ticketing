<?php

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a regular user cannot manage categories', function (string $method, string $url) {
    Category::factory()->create(['name' => 'Billing', 'slug' => 'billing']);

    $this->actingAs(User::factory()->create())->{$method}($url, ['name' => 'Hijacked'])->assertForbidden();

    expect(Category::pluck('name')->all())->toBe(['Billing']);
})->with([
    'list' => ['get', '/admin/categories'],
    'form' => ['get', '/admin/categories/create'],
    'creation' => ['post', '/admin/categories'],
    'update' => ['put', '/admin/categories/1'],
    'deletion' => ['delete', '/admin/categories/1'],
]);

test('an admin sees the categories with their ticket counts', function () {
    $category = Category::factory()->create(['name' => 'Billing']);
    Ticket::factory(3)->for($category)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.categories.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Taxonomy/Index')
            ->where('resource.baseUrl', '/admin/categories')
            ->where('items.0.name', 'Billing')
            ->where('items.0.count', 3));
});

test('an admin can create a category and its slug comes from the name', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), ['name' => 'Hardware & Devices', 'description' => 'Laptops and printers.'])
        ->assertRedirect(route('admin.categories.index'));

    $category = Category::sole();

    expect($category->name)->toBe('Hardware & Devices');
    expect($category->slug)->toBe('hardware-devices');
    expect($category->description)->toBe('Laptops and printers.');
});

test('a category name must be unique and meaningful', function (string $name, string $message) {
    Category::factory()->create(['name' => 'Billing', 'slug' => 'billing']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.categories.store'), ['name' => $name])
        ->assertSessionHasErrors(['name' => $message]);

    expect(Category::count())->toBe(1);
})->with([
    'a duplicate' => ['billing', 'A category with this name already exists.'],
    'only symbols' => ['***', 'The name must contain letters or numbers.'],
    'nothing' => ['', 'The name field is required.'],
]);

test('an admin can rename a category and may keep its own name', function () {
    $category = Category::factory()->create(['name' => 'Billing', 'slug' => 'billing']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.categories.update', $category), ['name' => 'Billing', 'description' => 'Updated'])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)->put(route('admin.categories.update', $category), ['name' => 'Payments']);

    expect($category->fresh()->slug)->toBe('payments');
});

test('deleting a category keeps its tickets without a category', function () {
    $category = Category::factory()->create();
    $ticket = Ticket::factory()->for($category)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    expect(Category::count())->toBe(0);
    expect($ticket->fresh()->category_id)->toBeNull();
});
