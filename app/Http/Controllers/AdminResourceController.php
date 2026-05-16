<?php

namespace App\Http\Controllers;

use App\Models\AboutSubject;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\Media;
use App\Models\Message;
use App\Models\Pricing;
use App\Models\Reason;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminResourceController extends Controller
{
    private array $resources = [
        'categories' => [
            'model' => Category::class,
            'title' => 'Categories',
            'icon' => 'bi-tags',
            'primary' => 'category_name',
            'fields' => [
                ['name' => 'category_name', 'label' => 'Category name', 'type' => 'translatable', 'required' => true],
                ['name' => 'category_description', 'label' => 'Category description', 'type' => 'translatable-textarea'],
            ],
            'columns' => ['category_name', 'category_description', 'created_at'],
        ],
        'roles' => [
            'model' => Role::class,
            'title' => 'Roles',
            'icon' => 'bi-person-badge',
            'primary' => 'role_name',
            'fields' => [
                ['name' => 'role_name', 'label' => 'Role name', 'type' => 'translatable', 'required' => true],
                ['name' => 'role_description', 'label' => 'Role description', 'type' => 'translatable-textarea'],
            ],
            'columns' => ['role_name', 'role_description', 'created_at'],
        ],
        'reasons' => [
            'model' => Reason::class,
            'title' => 'Report reasons',
            'icon' => 'bi-flag',
            'primary' => 'reason_content',
            'fields' => [
                ['name' => 'reason_content', 'label' => 'Reason content', 'type' => 'translatable-textarea', 'required' => true],
                ['name' => 'entity', 'label' => 'Entity', 'type' => 'select', 'options' => ['media' => 'Media', 'user' => 'User'], 'required' => true],
            ],
            'columns' => ['reason_content', 'entity', 'created_at'],
        ],
        'pricings' => [
            'model' => Pricing::class,
            'title' => 'Pricings',
            'icon' => 'bi-cash-coin',
            'primary' => 'pricing_name',
            'group_by' => 'pricing_type',
            'fields' => [
                ['name' => 'pricing_name', 'label' => 'Pricing name', 'type' => 'translatable', 'required' => true],
                ['name' => 'pricing_type', 'label' => 'Pricing type', 'type' => 'select', 'options' => ['money' => 'Money', 'percentage' => 'Percentage'], 'required' => true],
                ['name' => 'reason', 'label' => 'Reason', 'type' => 'select', 'options' => ['' => '-', 'media_boost' => 'Media boost', 'ad' => 'Ad', 'gift_sent' => 'Gift sent', 'user_certfied' => 'User certified']],
                ['name' => 'pricing_cost', 'label' => 'Cost', 'type' => 'number', 'step' => '0.01'],
            ],
            'columns' => ['pricing_name', 'pricing_type', 'reason', 'pricing_cost'],
        ],
        'abouts' => [
            'model' => AboutSubject::class,
            'title' => 'Legal infos',
            'icon' => 'bi-info-circle',
            'primary' => 'subject',
            'fields' => [
                ['name' => 'subject', 'label' => 'Subject', 'type' => 'translatable'],
                ['name' => 'subject_description', 'label' => 'Subject description', 'type' => 'translatable-textarea', 'required' => true],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['selected' => 'Selected', 'rejected' => 'Rejected']],
            ],
            'columns' => ['subject', 'subject_description', 'status'],
        ],
        'videos' => [
            'model' => Media::class,
            'title' => 'Videos',
            'icon' => 'bi-play-btn',
            'primary' => 'media_title',
            'fields' => [
                ['name' => 'media_title', 'label' => 'Video title', 'type' => 'text'],
                ['name' => 'media_description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'media_url', 'label' => 'Video URL', 'type' => 'text'],
                ['name' => 'author_names', 'label' => 'Author names', 'type' => 'text'],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['film_series' => 'Film/series', 'comedy' => 'Comedy', 'music' => 'Music', 'education' => 'Education', 'business' => 'Business', 'crafts_diy' => 'Crafts/DIY', 'sports' => 'Sports', 'documentary' => 'Documentary'], 'required' => true],
                ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'is_free', 'label' => 'Free', 'type' => 'checkbox'],
                ['name' => 'for_youth', 'label' => 'For youth', 'type' => 'checkbox'],
                ['name' => 'user_id', 'label' => 'User ID', 'type' => 'number'],
            ],
            'columns' => ['media_title', 'type', 'price', 'is_free', 'created_at'],
        ],
        'users' => [
            'model' => User::class,
            'title' => 'Users',
            'icon' => 'bi-people',
            'primary' => 'email',
            'fields' => [
                ['name' => 'firstname', 'label' => 'First name', 'type' => 'text'],
                ['name' => 'lastname', 'label' => 'Last name', 'type' => 'text'],
                ['name' => 'surname', 'label' => 'Surname', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Username', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['created' => 'Created', 'activated' => 'Activated', 'disabled' => 'Disabled', 'blocked' => 'Blocked', 'deleted' => 'Deleted']],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['uncertified' => 'Uncertified', 'certified' => 'Certified']],
            ],
            'columns' => ['firstname', 'lastname', 'email', 'phone', 'status'],
        ],
        'messages' => [
            'model' => Message::class,
            'title' => 'Public messages',
            'icon' => 'bi-envelope',
            'primary' => 'message_content',
            'fields' => [
                ['name' => 'message_content', 'label' => 'Message content', 'type' => 'textarea'],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['read' => 'Read', 'unread' => 'Unread']],
                ['name' => 'user_id', 'label' => 'Sender ID', 'type' => 'number'],
                ['name' => 'addressee_user_id', 'label' => 'Addressee user ID', 'type' => 'number'],
                ['name' => 'addressee_group_id', 'label' => 'Addressee group ID', 'type' => 'number'],
            ],
            'columns' => ['message_content', 'status', 'user_id', 'created_at'],
        ],
        'notifications' => [
            'model' => AdminNotification::class,
            'title' => 'Notifications',
            'icon' => 'bi-bell',
            'primary' => 'type',
            'readonly' => true,
            'columns' => ['type', 'is_read', 'from_user_id', 'to_user_id', 'media_id', 'created_at'],
        ],
    ];

    public function dashboard()
    {
        return view('admin.dashboard', [
            'stats' => [
                'videos' => Media::count(),
                'users' => User::count(),
                'categories' => Category::count(),
                'notifications' => AdminNotification::where('is_read', 0)->count(),
            ],
        ]);
    }

    public function index(string $resource)
    {
        return view('admin.resource', ['resource' => $resource, 'config' => $this->config($resource)]);
    }

    public function list(string $resource)
    {
        $config = $this->config($resource);
        $query = $config['model']::query()->latest('id');

        return response()->json([
            'items' => $query->limit(200)->get()->map(fn (Model $item) => $this->present($item, $config))->values(),
            'group_by' => $config['group_by'] ?? null,
        ]);
    }

    public function show(string $resource, int $id)
    {
        $config = $this->config($resource);

        return response()->json($config['model']::findOrFail($id));
    }

    public function store(Request $request, string $resource)
    {
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);

        $item = new $config['model']();
        $item->fill($this->payload($request, $config, $item));
        $item->save();

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($item, $config)]);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);

        $item = $config['model']::findOrFail($id);
        $item->fill($this->payload($request, $config, $item));
        $item->save();

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($item, $config)]);
    }

    public function destroy(string $resource, int $id)
    {
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);
        $config['model']::findOrFail($id)->delete();

        return response()->json(['message' => __('admin.deleted')]);
    }

    public function account()
    {
        return view('admin.account');
    }

    private function config(string $resource): array
    {
        abort_unless(isset($this->resources[$resource]), 404);

        return $this->resources[$resource];
    }

    private function payload(Request $request, array $config, Model $item): array
    {
        $payload = [];

        foreach ($config['fields'] ?? [] as $field) {
            $name = $field['name'];
            $type = $field['type'];

            if (str_starts_with($type, 'translatable')) {
                $payload[$name] = [
                    'fr' => $request->input($name.'.fr'),
                    'en' => $request->input($name.'.en'),
                    'ln' => $request->input($name.'.ln'),
                ];
                continue;
            }

            if ($type === 'checkbox') {
                $payload[$name] = $request->boolean($name);
                continue;
            }

            if ($type === 'password') {
                if ($request->filled($name)) {
                    $payload[$name] = Hash::make($request->input($name));
                }
                continue;
            }

            $payload[$name] = $request->input($name);
        }

        return $payload;
    }

    private function present(Model $item, array $config): array
    {
        $raw = $item->toArray();
        $locale = app()->getLocale();

        foreach ($config['columns'] as $column) {
            $value = Arr::get($raw, $column);
            if (is_array($value)) {
                $raw[$column.'_display'] = $value[$locale] ?? $value['fr'] ?? reset($value);
            } else {
                $decoded = is_string($value) ? json_decode($value, true) : null;
                $raw[$column.'_display'] = is_array($decoded) ? ($decoded[$locale] ?? $decoded['fr'] ?? reset($decoded)) : $value;
            }
        }

        return $raw;
    }
}
