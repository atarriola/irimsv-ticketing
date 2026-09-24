<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * The wording and addresses the shared admin pages need for categories.
     *
     * @var array{singular: string, plural: string, baseUrl: string, description: string, countLabel: string, hasPosition: bool, deleteWarning: string}
     */
    private const array RESOURCE = [
        'singular' => 'category',
        'plural' => 'Ticket categories',
        'baseUrl' => '/admin/categories',
        'description' => 'Categories help sort tickets by subject.',
        'countLabel' => 'tickets',
        'hasPosition' => false,
        'deleteWarning' => 'Its tickets will be kept but left without a category.',
    ];

    /**
     * Display the ticket categories.
     */
    public function index(): Response
    {
        Gate::authorize('create', Category::class);

        $items = Category::withCount('tickets')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'key' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'count' => $category->tickets_count,
            ]);

        return Inertia::render('Admin/Taxonomy/Index', ['resource' => self::RESOURCE, 'items' => $items]);
    }

    /**
     * Display the form for creating a category.
     */
    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('Admin/Taxonomy/Form', ['resource' => self::RESOURCE, 'item' => null]);
    }

    /**
     * Create a category.
     */
    public function store(SaveCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->categoryAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The {$category->name} category has been created."]);

        return redirect()->route('admin.categories.index');
    }

    /**
     * Display the form for editing a category.
     */
    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('Admin/Taxonomy/Form', [
            'resource' => self::RESOURCE,
            'item' => [
                'key' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
            ],
        ]);
    }

    /**
     * Update a category.
     */
    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->categoryAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => "The {$category->name} category has been updated."]);

        return redirect()->route('admin.categories.index');
    }

    /**
     * Delete a category, leaving its tickets uncategorised.
     */
    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "The {$category->name} category has been deleted."]);

        return redirect()->route('admin.categories.index');
    }
}
