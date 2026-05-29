<?php

namespace App\Http\Controllers;

use App\Models\AboutSubject;
use App\Models\AboutTitle;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\Comment;
use App\Models\File;
use App\Models\Media;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Reason;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminResourceController extends Controller
{
    private array $resources = [
        'categories' => [
            'model' => Category::class,
            'title' => 'Catégories',
            'icon' => 'bi-tags',
            'primary' => 'category_name',
            'group_by' => 'for_type',
            'fields' => [
                ['name' => 'category_name', 'label' => 'Nom de catégorie', 'type' => 'translatable', 'required' => true],
                ['name' => 'category_description', 'label' => 'Description de catégorie', 'type' => 'translatable-textarea'],
                ['name' => 'icon', 'label' => 'Icône', 'type' => 'text'],
                ['name' => 'color', 'label' => 'Couleur', 'type' => 'text'],
                ['name' => 'for_type', 'label' => 'Type concerné', 'type' => 'select', 'options' => self::CONTENT_TYPE_OPTIONS, 'required' => true],
            ],
            'columns' => ['category_name', 'category_description', 'icon', 'created_at'],
            'table_labels' => [
                'category_name' => 'Nom',
                'category_description' => 'Description',
            ],
        ],
        'roles' => [
            'model' => Role::class,
            'title' => 'Rôles',
            'icon' => 'bi-person-badge',
            'primary' => 'role_name',
            'fields' => [
                ['name' => 'role_name', 'label' => 'Nom du role', 'type' => 'translatable', 'required' => true],
                ['name' => 'role_description', 'label' => 'Description du role', 'type' => 'translatable-textarea'],
            ],
            'columns' => ['role_name', 'role_description', 'created_at'],
        ],
        'reasons' => [
            'model' => Reason::class,
            'title' => 'Motifs de signalement',
            'icon' => 'bi-flag',
            'primary' => 'reason_content',
            'group_by' => 'entity',
            'fields' => [
                ['name' => 'reason_content', 'label' => 'Contenu du motif', 'type' => 'translatable-textarea', 'required' => true],
                ['name' => 'entity', 'label' => 'Élément concerné', 'type' => 'select', 'options' => ['media' => 'Vidéo', 'product' => 'Produit', 'user' => 'Utilisateur'], 'required' => true],
            ],
            'columns' => ['reason_content', 'entity', 'created_at'],
        ],
        'pricings' => [
            'model' => Pricing::class,
            'title' => 'Tarifications',
            'icon' => 'bi-cash-coin',
            'primary' => 'pricing_name',
            'group_by' => 'pricing_type',
            'with' => ['descriptions'],
            'children' => 'pricing_descriptions',
            'forced' => ['currency' => 'USD'],
            'fields' => [
                ['name' => 'pricing_name', 'label' => 'Nom de la tarification', 'type' => 'translatable', 'required' => true],
                ['name' => 'pricing_type', 'label' => 'Type de tarification', 'type' => 'select', 'options' => ['money' => 'Montant fixe', 'percentage' => 'Pourcentage'], 'required' => true],
                ['name' => 'reason', 'label' => 'Motif', 'type' => 'select', 'options' => ['' => '-', 'media_boost' => 'Boost de vidéo', 'ad' => 'Publicité', 'gift_sent' => 'Cadeau envoyé', 'user_certfied' => 'Certification utilisateur']],
                ['name' => 'pricing_cost', 'label' => 'Coût (en USD)', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'currency', 'label' => 'Devise', 'type' => 'hidden', 'value' => 'USD'],
                ['name' => 'image_url', 'label' => 'URL de l image', 'type' => 'text'],
                ['name' => 'icon', 'label' => 'Icône', 'type' => 'text'],
                ['name' => 'color', 'label' => 'Couleur', 'type' => 'text'],
            ],
            'columns' => ['pricing_name', 'pricing_type', 'reason', 'pricing_cost', 'currency'],
        ],
        'abouts' => [
            'model' => AboutSubject::class,
            'title' => 'Infos légales',
            'icon' => 'bi-info-circle',
            'primary' => 'subject',
            'with' => ['titles.contents.dashes'],
            'children' => 'about_titles',
            'fields' => [
                ['name' => 'subject', 'label' => 'Sujet', 'type' => 'translatable'],
                ['name' => 'subject_description', 'label' => 'Description du sujet', 'type' => 'translatable-textarea', 'required' => true],
                ['name' => 'status', 'label' => 'Statut', 'type' => 'select', 'options' => ['selected' => 'Sélectionné', 'rejected' => 'Rejeté']],
            ],
            'columns' => ['subject', 'subject_description', 'status'],
        ],
        'videos' => [
            'model' => Media::class,
            'title' => 'Vidéos',
            'icon' => 'bi-play-btn',
            'primary' => 'media_title',
            'with' => ['user'],
            'fields' => [
                ['name' => 'media_title', 'label' => 'Titre de la vidéo', 'type' => 'translatable'],
                ['name' => 'media_description', 'label' => 'Description', 'type' => 'translatable-textarea'],
                ['name' => 'media_url', 'label' => 'Télécharger la vidéo', 'type' => 'file-url', 'accept' => 'video/*', 'directory' => 'medias/videos', 'required' => true, 'rules' => ['file', 'mimes:mp4,mov,avi,mkv,webm', 'max:512000']],
                ['name' => 'cover_url', 'label' => 'Télécharger la couverture', 'type' => 'file-url', 'accept' => 'image/*', 'directory' => 'medias/covers', 'required' => true, 'rules' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240']],
                ['name' => 'author_names', 'label' => 'Noms des auteurs', 'type' => 'text'],
                ['name' => 'is_free', 'label' => 'Gratuit', 'type' => 'checkbox'],
                ['name' => 'price', 'label' => 'Prix', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'for_youth', 'label' => 'Pour les jeunes', 'type' => 'checkbox'],
                ['name' => 'belongs_to', 'label' => 'Dépend de la vidéo ID', 'type' => 'number'],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => [
                    'film_series' => 'Films et séries',
                    'comedy' => 'Comédie',
                    'music' => 'Musique',
                    'education' => 'Éducation',
                    'business' => 'Business',
                    'crafts_diy' => 'Métiers et Bricolage',
                    'sports' => 'Sports',
                    'documentary' => 'Documentaire',
                ], 'required' => true],
                ['name' => 'is_shared', 'label' => 'Publier', 'type' => 'checkbox'],
                ['name' => 'user_id', 'label' => 'Utilisateur ID', 'type' => 'number'],
            ],
            'columns' => ['media_title', 'type', 'price', 'is_free', 'is_shared', 'created_at'],
            'shareable' => true,
        ],
        'products' => [
            'model' => Product::class,
            'title' => 'Produits',
            'icon' => 'bi-bag',
            'primary' => 'product_name',
            'fields' => [
                ['name' => 'product_name', 'label' => 'Nom du produit', 'type' => 'text', 'required' => true],
                ['name' => 'product_description', 'label' => 'Description du produit', 'type' => 'textarea'],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['product' => 'Produit', 'service' => 'Service'], 'required' => true],
                ['name' => 'quantity', 'label' => 'Quantité', 'type' => 'number'],
                ['name' => 'price', 'label' => 'Prix', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'currency', 'label' => 'Devise', 'type' => 'text'],
                ['name' => 'action', 'label' => 'Action', 'type' => 'select', 'options' => ['sale' => 'Vente', 'rental' => 'Location'], 'required' => true],
                ['name' => 'is_shared', 'label' => 'Publier', 'type' => 'checkbox'],
                ['name' => 'price_reduction_start', 'label' => 'Début réduction', 'type' => 'datetime-local'],
                ['name' => 'price_reduction_end', 'label' => 'Fin réduction', 'type' => 'datetime-local'],
                ['name' => 'reduction_rate', 'label' => 'Taux de réduction', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'category_id', 'label' => 'Categorie ID', 'type' => 'number'],
            ],
            'columns' => ['product_name', 'type', 'quantity', 'price', 'currency', 'action', 'is_shared', 'created_at'],
            'shareable' => true,
        ],
        'app-infos' => [
            'model' => Comment::class,
            'title' => 'Fonctionnalités de la plateforme',
            'icon' => 'bi-info-square',
            'primary' => 'comment_content',
            'where' => ['type' => 'app_info'],
            'with' => ['files'],
            'has_files' => true,
            'fields' => [
                ['name' => 'comment_content', 'label' => 'Description de la fonctionnalité', 'type' => 'textarea', 'required' => true],
                ['name' => 'for_entity', 'label' => 'Espace concerné', 'type' => 'select', 'options' => ['user' => 'Utilisateur', 'media' => 'Vidéo', 'product' => 'Produit', 'message' => 'Message'], 'required' => true],
                ['name' => 'files', 'label' => 'Images', 'type' => 'file-multiple', 'accept' => 'image/*'],
            ],
            'columns' => ['comment_content', 'for_entity', 'files_count', 'created_at'],
            'forced' => ['type' => 'app_info'],
        ],
        'users' => [
            'model' => User::class,
            'title' => 'Utilisateurs',
            'icon' => 'bi-people',
            'primary' => 'email',
            'with' => ['roles'],
            'role_filter' => true,
            'fields' => [
                ['name' => 'firstname', 'label' => 'Prénom', 'type' => 'text'],
                ['name' => 'lastname', 'label' => 'Nom', 'type' => 'text'],
                ['name' => 'surname', 'label' => 'Postnom', 'type' => 'text'],
                ['name' => 'partner_name', 'label' => 'Nom du partenaire', 'type' => 'text'],
                ['name' => 'gender', 'label' => 'Genre', 'type' => 'select', 'options' => ['' => '-', 'male' => 'Masculin', 'female' => 'Feminin']],
                ['name' => 'birthdate', 'label' => 'Date de naissance', 'type' => 'date'],
                ['name' => 'country', 'label' => 'Pays', 'type' => 'text'],
                ['name' => 'city', 'label' => 'Ville', 'type' => 'text'],
                ['name' => 'address_1', 'label' => 'Adresse 1', 'type' => 'textarea'],
                ['name' => 'address_2', 'label' => 'Adresse 2', 'type' => 'textarea'],
                ['name' => 'p_o_box', 'label' => 'Boite postale', 'type' => 'text'],
                ['name' => 'currency', 'label' => 'Devise', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'phone', 'label' => 'Téléphone', 'type' => 'text'],
                ['name' => 'username', 'label' => 'Nom d’utilisateur', 'type' => 'text'],
                ['name' => 'password', 'label' => 'Mot de passe', 'type' => 'password'],
                ['name' => 'password_confirmation', 'label' => 'Confirmation du mot de passe', 'type' => 'password'],
                ['name' => 'promo_code', 'label' => 'Code promotionnel', 'type' => 'text'],
                ['name' => 'tips_at_every_login', 'label' => 'Conseils à chaque connexion', 'type' => 'checkbox'],
                ['name' => 'is_online', 'label' => 'En ligne', 'type' => 'checkbox'],
                ['name' => 'christian_preference', 'label' => 'Préférence chrétienne', 'type' => 'checkbox'],
                ['name' => 'status', 'label' => 'Statut', 'type' => 'select', 'options' => ['created' => 'Créé', 'activated' => 'Activé', 'disabled' => 'Désactivé', 'blocked' => 'Bloqué', 'deleted' => 'Supprimé']],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['uncertified' => 'Non certifié', 'certified' => 'Certifié']],
                ['name' => 'role_id', 'label' => 'Rôle', 'type' => 'select', 'options' => []],
            ],
            'columns' => ['firstname', 'lastname', 'email', 'phone', 'status'],
            'table_labels' => [
                'status' => 'État',
            ],
            'status_editable' => true,
            'avatar_cropper' => true,
        ],
        'messages' => [
            'model' => Message::class,
            'title' => 'Messages du public',
            'icon' => 'bi-envelope',
            'primary' => 'message_content',
            'fields' => [
                ['name' => 'message_content', 'label' => 'Contenu du message', 'type' => 'textarea'],
                ['name' => 'answered_for', 'label' => 'Réponse au message ID', 'type' => 'number'],
                ['name' => 'status', 'label' => 'Statut', 'type' => 'select', 'options' => ['read' => 'Lu', 'unread' => 'Non lu']],
                ['name' => 'user_id', 'label' => 'Expéditeur ID', 'type' => 'number'],
                ['name' => 'addressee_user_id', 'label' => 'Destinataire utilisateur ID', 'type' => 'number'],
                ['name' => 'addressee_group_id', 'label' => 'Destinataire groupe ID', 'type' => 'number'],
            ],
            'columns' => ['message_content', 'status', 'user_id', 'created_at'],
        ],
        'notifications' => [
            'model' => AdminNotification::class,
            'title' => 'Notifications',
            'icon' => 'bi-bell',
            'primary' => 'type',
            'readonly' => true,
            'with' => ['fromUser', 'toUser', 'media', 'product', 'comment'],
            'current_user_only' => true,
            'social_feed' => true,
            'columns' => ['message', 'type', 'is_read', 'created_at'],
        ],
    ];

    private const CONTENT_TYPE_OPTIONS = [
        'film_series' => 'Films et séries',
        'comedy' => 'Comédie',
        'music' => 'Musique',
        'education' => 'Éducation',
        'business' => 'Business',
        'crafts_diy' => 'Métiers et Bricolage',
        'sports' => 'Sports',
        'documentary' => 'Documentaire',
        'product' => 'Produit',
        'service' => 'Service',
    ];

    public function dashboard()
    {
        return view('admin.dashboard', [
            'stats' => $this->dashboardStats(),
            'paymentStats' => $this->paymentStats(),
            'paymentTrend' => $this->paymentTrend(),
            'recentUsers' => $this->recentMemberUsers(),
            'recentVideos' => $this->recentItems(Media::class, ['user']),
            'recentProducts' => $this->recentItems(Product::class, ['user']),
            'statusOptions' => $this->userStatusOptions(),
        ]);
    }

    public function index(string $resource)
    {
        return view('admin.resource', ['resource' => $resource, 'config' => $this->config($resource)]);
    }

    public function list(string $resource)
    {
        $config = $this->config($resource);
        $query = $config['model']::query()
            ->when(! empty($config['with']), fn ($query) => $query->with($config['with']))
            ->when(! empty($config['where']), fn ($query) => $query->where($config['where']))
            ->when(! empty($config['current_user_only']), fn ($query) => $query->where('to_user_id', request()->user()?->id))
            ->latest('id');

        return response()->json([
            'items' => $query->limit(200)->get()->map(fn (Model $item) => $this->present($item, $config))->values(),
            'group_by' => $config['group_by'] ?? null,
        ]);
    }

    public function show(string|int $resource, string|int|null $id = null)
    {
        [$resource, $id] = $this->routeArguments($resource, $id);
        $config = $this->config($resource);

        $item = $config['model']::query()
            ->when(! empty($config['with']), fn ($query) => $query->with($config['with']))
            ->when(! empty($config['where']), fn ($query) => $query->where($config['where']))
            ->when(! empty($config['current_user_only']), fn ($query) => $query->where('to_user_id', request()->user()?->id))
            ->findOrFail($id);

        return response()->json($this->present($item, $config));
    }

    public function store(Request $request, string $resource)
    {
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);
        $this->validateFileUrlUploads($request, $config);
        $this->validateUserPayload($request, $config);

        $item = DB::transaction(function () use ($request, $config): Model {
            $item = new $config['model'];
            $item->fill($this->payload($request, $config, $item));
            $this->fillUserAvatar($request, $item);
            $item->save();
            $this->saveChildren($request, $config, $item);
            $this->saveFiles($request, $config, $item);
            $this->saveUserRole($request, $item);

            return $item;
        });

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($item, $config)]);
    }

    public function update(Request $request, string|int $resource, string|int|null $id = null)
    {
        [$resource, $id] = $this->routeArguments($resource, $id);
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);
        $this->validateFileUrlUploads($request, $config);
        $this->validateUserPayload($request, $config);

        $item = DB::transaction(function () use ($request, $config, $id): Model {
            $item = $config['model']::findOrFail($id);
            $item->fill($this->payload($request, $config, $item));
            $this->fillUserAvatar($request, $item);
            $item->save();
            $this->saveChildren($request, $config, $item);
            $this->saveFiles($request, $config, $item);
            $this->saveUserRole($request, $item);

            return $item;
        });

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($item, $config)]);
    }

    public function toggleShared(string|int $resource, string|int|null $id = null)
    {
        [$resource, $id] = $this->routeArguments($resource, $id);
        $config = $this->config($resource);
        abort_unless($config['shareable'] ?? false, 404);

        $item = $config['model']::findOrFail($id);
        $item->is_shared = ! $item->is_shared;
        $item->save();

        if ($item->is_shared) {
            AdminNotification::create([
                'type' => $item instanceof Media ? 'media_accepted' : 'product_accepted',
                'to_user_id' => $item->user_id ?? null,
                'media_id' => $item instanceof Media ? $item->id : null,
                'product_id' => $item instanceof Product ? $item->id : null,
            ]);
        }

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($item, $config)]);
    }

    public function markNotificationAsRead(int $id)
    {
        $notification = AdminNotification::query()
            ->where('to_user_id', request()->user()?->id)
            ->findOrFail($id);

        $notification->is_read = true;
        $notification->save();

        return response()->json(['message' => __('admin.notification_read'), 'item' => $this->present($notification->load(['fromUser', 'toUser', 'media', 'product', 'comment']), $this->config('notifications'))]);
    }

    public function updateUserStatus(Request $request, int $id)
    {
        $config = $this->config('users');
        $statusOptions = collect($config['fields'])->firstWhere('name', 'status')['options'];

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys($statusOptions))],
        ]);

        $user = User::query()->with('roles')->findOrFail($id);
        $user->status = $validated['status'];
        $user->save();

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($user->refresh()->load('roles'), $config)]);
    }

    public function destroy(string|int $resource, string|int|null $id = null)
    {
        [$resource, $id] = $this->routeArguments($resource, $id);
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);
        $config['model']::findOrFail($id)->delete();

        return response()->json(['message' => __('admin.deleted')]);
    }

    public function account(Request $request)
    {
        return view('admin.account', [
            'user' => $request->user(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int, display_value: string, icon: string, color: string, url: string}>
     */
    private function dashboardStats(): array
    {
        $stats = [
            [
                'label' => 'Vidéos publiées',
                'value' => $this->countByShared(Media::class, true),
                'icon' => 'bi-play-circle',
                'color' => 'primary',
                'url' => route('videos.index'),
            ],
            [
                'label' => 'Vidéos non publiées',
                'value' => $this->countByShared(Media::class, false),
                'icon' => 'bi-play-btn',
                'color' => 'warning',
                'url' => route('videos.index'),
            ],
            [
                'label' => 'Produits publiés',
                'value' => $this->countByShared(Product::class, true),
                'icon' => 'bi-bag-check',
                'color' => 'success',
                'url' => route('products.index'),
            ],
            [
                'label' => 'Produits non publiés',
                'value' => $this->countByShared(Product::class, false),
                'icon' => 'bi-bag-x',
                'color' => 'danger',
                'url' => route('products.index'),
            ],
            [
                'label' => 'Utilisateurs',
                'value' => Schema::hasTable('users') ? User::count() : 0,
                'icon' => 'bi-people',
                'color' => 'info',
                'url' => route('users.index'),
            ],
            [
                'label' => 'Catégories',
                'value' => Schema::hasTable('categories') ? Category::count() : 0,
                'icon' => 'bi-tags',
                'color' => 'secondary',
                'url' => route('categories.index'),
            ],
        ];

        return collect($stats)
            ->map(fn (array $stat): array => $stat + ['display_value' => $this->compactDashboardNumber($stat['value'])])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function paymentStats(): array
    {
        if (! Schema::hasTable('payments')) {
            return [
                'pending' => 0,
                'successful' => 0,
                'failed' => 0,
            ];
        }

        return [
            'pending' => Payment::query()->where('status', 1)->count(),
            'successful' => Payment::query()->where('status', 0)->count(),
            'failed' => Payment::query()->where('status', 2)->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, pending: array<int, int>, successful: array<int, int>, failed: array<int, int>}
     */
    private function paymentTrend(): array
    {
        $fallback = [
            'labels' => collect(range(6, 0))->map(fn (int $days): string => now()->subDays($days)->format('d/m'))->all(),
            'pending' => array_fill(0, 7, 0),
            'successful' => array_fill(0, 7, 0),
            'failed' => array_fill(0, 7, 0),
        ];

        if (! Schema::hasTable('payments')) {
            return $fallback;
        }

        $dates = collect(range(6, 0))
            ->map(fn (int $days) => now()->subDays($days)->toDateString());
        $rows = Payment::query()
            ->selectRaw('DATE(created_at) as payment_date, status, COUNT(*) as total')
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->whereIn('status', [0, 1, 2])
            ->groupBy('payment_date', 'status')
            ->get()
            ->groupBy(fn ($row): string => $row->payment_date.'-'.$row->status);

        return [
            'labels' => $dates->map(fn (string $date): string => Carbon::parse($date)->format('d/m'))->all(),
            'pending' => $dates->map(fn (string $date): int => (int) ($rows->get($date.'-1')?->first()?->total ?? 0))->all(),
            'successful' => $dates->map(fn (string $date): int => (int) ($rows->get($date.'-0')?->first()?->total ?? 0))->all(),
            'failed' => $dates->map(fn (string $date): int => (int) ($rows->get($date.'-2')?->first()?->total ?? 0))->all(),
        ];
    }

    private function compactDashboardNumber(int $value): string
    {
        if ($value < 1000) {
            return number_format($value, 0, ',', ' ');
        }

        $units = [
            1000000000 => 'B',
            1000000 => 'M',
            1000 => 'k',
        ];

        foreach ($units as $unitValue => $suffix) {
            if ($value < $unitValue) {
                continue;
            }

            $compact = min(100, 10 ** (int) floor(log10((int) floor($value / $unitValue))));

            return 'Plus de '.$compact.$suffix;
        }

        return (string) $value;
    }

    private function recentMemberUsers(): Collection
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            return collect();
        }

        return User::query()
            ->with('roles')
            ->whereHas('roles', fn ($query) => $query
                ->where('role_name->fr', 'Membre')
                ->where('role_user.is_selected', true))
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function recentItems(string $model, array $with = []): Collection
    {
        $table = (new $model)->getTable();
        if (! Schema::hasTable($table)) {
            return collect();
        }

        return $model::query()
            ->when($with !== [], fn ($query) => $query->with($with))
            ->latest('id')
            ->limit(5)
            ->get();
    }

    private function countByShared(string $model, bool $shared): int
    {
        $table = (new $model)->getTable();
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return $model::query()->where('is_shared', $shared)->count();
    }

    private function config(string $resource): array
    {
        abort_unless(isset($this->resources[$resource]), 404);

        $config = $this->resources[$resource];

        if ($resource === 'users') {
            $config = $this->withRoleOptions($config);
        }

        return $config;
    }

    private function withRoleOptions(array $config): array
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            $config['with'] = [];
        }

        $partnerRole = $this->ensurePartnerRole();
        $options = $this->roleOptions(exceptRoleId: $partnerRole?->id);

        foreach ($config['fields'] as &$field) {
            if ($field['name'] === 'role_id') {
                $field['options'] = $options;
            }
        }

        $config['role_filter_options'] = $this->roleOptions();
        $config['partner_role_id'] = $partnerRole?->id;
        $config['user_modes'] = [
            'user' => [
                'label' => 'Ajouter utilisateur',
                'hidden' => ['partner_name'],
            ],
            'partner' => [
                'label' => 'Ajouter partenaire',
                'hidden' => ['firstname', 'lastname', 'surname', 'gender', 'birthdate', 'address_2', 'role_id'],
            ],
        ];

        return $config;
    }

    /**
     * @return array<string, string>
     */
    private function userStatusOptions(): array
    {
        return collect($this->resources['users']['fields'])
            ->firstWhere('name', 'status')['options'];
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(?int $exceptRoleId = null): array
    {
        if (! Schema::hasTable('roles')) {
            return ['' => '-'];
        }

        return ['' => '-'] + Role::query()
            ->when($exceptRoleId !== null, fn ($query) => $query->whereKeyNot($exceptRoleId))
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (Role $role): array => [
                (string) $role->id => $role->getTranslation('role_name', app()->getLocale(), false) ?: $role->getTranslation('role_name', 'fr', false) ?: (string) $role->id,
            ])
            ->all();
    }

    private function ensurePartnerRole(): ?Role
    {
        if (! Schema::hasTable('roles')) {
            return null;
        }

        return Role::query()->where('role_name->fr', 'Partenaire')->first()
            ?? Role::create([
                'role_name' => [
                    'fr' => 'Partenaire',
                    'en' => 'Partner',
                    'ln' => 'Moninga ya mosala',
                ],
                'role_description' => [
                    'fr' => 'Personne qui paie pour faire la publicité sur la plateforme.',
                    'en' => 'Person who pays to advertise on the platform.',
                    'ln' => 'Moto oyo afutaka mpo na kosala piblisite na plateforme.',
                ],
            ]);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function routeArguments(string|int $resource, string|int|null $id): array
    {
        if (! isset($this->resources[(string) $resource]) && isset($this->resources[(string) $id])) {
            return [(string) $id, (int) $resource];
        }

        return [(string) $resource, (int) $id];
    }

    private function payload(Request $request, array $config, Model $item): array
    {
        $payload = [];

        foreach ($config['fields'] ?? [] as $field) {
            $name = $field['name'];
            $type = $field['type'];

            if ($type === 'file-multiple') {
                continue;
            }

            if ($item instanceof User && $name === 'password_confirmation') {
                continue;
            }

            if ($type === 'file-url') {
                if ($request->hasFile($name)) {
                    $payload[$name] = Storage::disk('s3')->url($request->file($name)->store($field['directory'] ?? 'uploads', 's3'));
                }

                continue;
            }

            if ($item instanceof User && $name === 'role_id') {
                continue;
            }

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

        foreach ($config['forced'] ?? [] as $name => $value) {
            $payload[$name] = $value;
        }

        if (($config['forced']['type'] ?? null) === 'app_info' && $item instanceof Comment) {
            $payload['user_id'] = $item->exists ? $item->user_id : $request->user()?->id;
        }

        if ($item instanceof User && ! $item->exists) {
            $payload['status'] ??= 'created';
            $payload['type'] ??= 'uncertified';
        }

        return $payload;
    }

    private function validateUserPayload(Request $request, array $config): void
    {
        if (($config['model'] ?? null) !== User::class) {
            return;
        }

        $request->validate([
            'password' => ['nullable', 'confirmed'],
            'avatar_base64' => ['nullable', 'string'],
        ]);
    }

    private function fillUserAvatar(Request $request, Model $item): void
    {
        if (! $item instanceof User || ! $request->filled('avatar_base64')) {
            return;
        }

        $image = (string) $request->input('avatar_base64');
        if (! preg_match('/^data:image\/(?:png|jpe?g|webp);base64,/', $image)) {
            return;
        }

        $binary = base64_decode((string) preg_replace('/^data:image\/(?:png|jpe?g|webp);base64,/', '', $image), true);
        if ($binary === false) {
            return;
        }

        $path = 'users/avatars/'.Str::uuid().'.png';
        Storage::disk('s3')->put($path, $binary);
        $item->avatar_url = Storage::disk('s3')->url($path);
    }

    private function validateFileUrlUploads(Request $request, array $config): void
    {
        $isUpdate = $request->isMethod('put') || $request->isMethod('patch') || $request->input('_method') === 'PUT';
        $rules = collect($config['fields'] ?? [])
            ->filter(fn (array $field): bool => ($field['type'] ?? null) === 'file-url')
            ->mapWithKeys(fn (array $field): array => [$field['name'] => array_merge([$isUpdate || empty($field['required']) ? 'nullable' : 'required'], $field['rules'] ?? ['file', 'max:10240'])])
            ->all();

        if ($rules !== []) {
            $request->validate($rules);
        }
    }

    private function saveFiles(Request $request, array $config, Model $item): void
    {
        if (empty($config['has_files']) || ! $item instanceof Comment) {
            return;
        }

        $request->validate([
            'files' => ['nullable', 'array'],
            'files.*' => ['image', 'max:5120'],
        ]);

        foreach ($request->file('files', []) as $uploadedFile) {
            File::create([
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_url' => Storage::disk('s3')->url($uploadedFile->store('app-infos', 's3')),
                'file_description' => $item->comment_content,
                'file_type' => 'photo',
                'user_id' => $item->user_id,
                'comment_id' => $item->id,
            ]);
        }
    }

    private function saveUserRole(Request $request, Model $item): void
    {
        if (! $item instanceof User || ! Schema::hasTable('role_user') || ! $request->filled('role_id')) {
            return;
        }

        $item->roles()->sync([(int) $request->input('role_id') => ['is_selected' => true]]);
    }

    private function saveChildren(Request $request, array $config, Model $item): void
    {
        if (($config['children'] ?? null) === 'pricing_descriptions') {
            $item->descriptions()->delete();

            foreach ($request->input('descriptions', []) as $description) {
                if (! $this->hasTranslatedValue($description['description_title'] ?? [])) {
                    continue;
                }

                $item->descriptions()->create([
                    'description_title' => $this->translatedPayload($description['description_title'] ?? []),
                    'description_content' => $this->translatedPayload($description['description_content'] ?? []),
                ]);
            }
        }

        if (($config['children'] ?? null) === 'about_titles') {
            $item->titles()->each(function (AboutTitle $title): void {
                $title->contents()->each(fn ($content) => $content->dashes()->delete());
                $title->contents()->delete();
            });
            $item->titles()->delete();

            foreach ($request->input('titles', []) as $titlePayload) {
                if (! $this->hasTranslatedValue($titlePayload['title'] ?? [])) {
                    continue;
                }

                $title = $item->titles()->create([
                    'title' => $this->translatedPayload($titlePayload['title'] ?? []),
                    'alias' => $titlePayload['alias'] ?? null,
                ]);

                foreach ($titlePayload['contents'] ?? [] as $contentPayload) {
                    if (! $this->hasTranslatedValue($contentPayload['content'] ?? [])) {
                        continue;
                    }

                    $content = $title->contents()->create([
                        'subtitle' => $this->translatedPayload($contentPayload['subtitle'] ?? []),
                        'content' => $this->translatedPayload($contentPayload['content'] ?? []),
                    ]);

                    foreach ($contentPayload['dashes'] ?? [] as $dashPayload) {
                        if (! $this->hasTranslatedValue($dashPayload['dash_content'] ?? [])) {
                            continue;
                        }

                        $content->dashes()->create([
                            'dash_content' => $this->translatedPayload($dashPayload['dash_content'] ?? []),
                            'belongs_to' => $dashPayload['belongs_to'] ?? null,
                        ]);
                    }
                }
            }
        }
    }

    private function translatedPayload(array $payload): array
    {
        return [
            'fr' => $payload['fr'] ?? null,
            'en' => $payload['en'] ?? null,
            'ln' => $payload['ln'] ?? null,
        ];
    }

    private function hasTranslatedValue(array $payload): bool
    {
        return collect($payload)->filter(fn ($value) => filled($value))->isNotEmpty();
    }

    private function present(Model $item, array $config): array
    {
        $raw = $item->toArray();
        $locale = app()->getLocale();

        foreach ($config['columns'] as $column) {
            $value = Arr::get($raw, $column);
            if ($column === 'files_count' && $item instanceof Comment) {
                $raw['files_count'] = $item->files->count();
                $raw['files_count_display'] = $item->files->count();

                continue;
            }

            if ($column === 'message' && $item instanceof AdminNotification) {
                $raw['message_display'] = $this->notificationMessage($item);
                $raw['url'] = $this->notificationUrl($item);
                $raw['sender_display'] = $this->notificationSenderName($item);

                continue;
            }

            if ($column === 'icon' && array_key_exists('color', $raw)) {
                $raw['icon_display'] = $value;
                $raw['icon_preview'] = [
                    'class' => $this->fontAwesomeIconClass($value),
                    'color' => $raw['color'] ?: '#6c757d',
                ];

                continue;
            }

            if ($column === 'price' && $item instanceof Media) {
                $raw['price_display'] = $this->mediaPriceDisplay($item);

                continue;
            }

            if ($this->isDateTimeColumn($column)) {
                $raw[$column.'_display'] = $this->dateTimeDisplay($value);
                $raw[$column.'_detail_display'] = $this->dateTimeDetailDisplay($value);

                continue;
            }

            $fieldOptions = $this->fieldOptions($config, $column);
            if ($fieldOptions !== null && ! is_array($value)) {
                $raw[$column.'_display'] = $fieldOptions[$value] ?? $value;
            } elseif (is_array($value)) {
                $raw[$column.'_display'] = $value[$locale] ?? $value['fr'] ?? reset($value);
            } else {
                $decoded = is_string($value) ? json_decode($value, true) : null;
                $raw[$column.'_display'] = is_array($decoded) ? ($decoded[$locale] ?? $decoded['fr'] ?? reset($decoded)) : $value;
            }
        }

        if ($item instanceof User && Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $selectedRole = $item->roles?->firstWhere('pivot.is_selected', true) ?? $item->roles?->first();
            $raw['role_id'] = $selectedRole?->id;
            $raw['role_id_display'] = $selectedRole?->getTranslation('role_name', $locale, false) ?: $selectedRole?->getTranslation('role_name', 'fr', false);
        }

        foreach ($raw as $column => $value) {
            if ($this->isDateTimeColumn((string) $column) && ! array_key_exists($column.'_display', $raw)) {
                $raw[$column.'_display'] = $this->dateTimeDisplay($value);
                $raw[$column.'_detail_display'] = $this->dateTimeDetailDisplay($value);
            }
        }

        return $raw;
    }

    private function fieldOptions(array $config, string $column): ?array
    {
        $field = collect($config['fields'] ?? [])->firstWhere('name', $column);

        return $field['options'] ?? null;
    }

    private function mediaPriceDisplay(Media $media): string
    {
        $price = (float) ($media->price ?? 0);
        $currency = $media->user?->currency ?: 'USD';

        if ($currency !== 'USD') {
            try {
                $price *= (float) getExchangeRate($currency, 'USD');
            } catch (\Throwable) {
                return number_format($price, 2, ',', ' ').' '.$currency;
            }
        }

        return number_format($price, 2, ',', ' ').' USD';
    }

    private function fontAwesomeIconClass(mixed $value): ?string
    {
        $icon = trim((string) $value);

        if ($icon === '') {
            return null;
        }

        if (str_contains($icon, ' ')) {
            return $icon;
        }

        return 'fa-solid '.(str_starts_with($icon, 'fa-') ? $icon : 'fa-'.$icon);
    }

    private function isDateTimeColumn(string $column): bool
    {
        return str_ends_with($column, '_at') || str_ends_with($column, '_start') || str_ends_with($column, '_end');
    }

    private function dateTimeDisplay(mixed $value): mixed
    {
        if (blank($value)) {
            return $value;
        }

        try {
            return formatAdminDateTime($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function dateTimeDetailDisplay(mixed $value): mixed
    {
        if (blank($value)) {
            return $value;
        }

        try {
            return 'Le '.Carbon::parse($value, config('app.timezone'))->timezone(config('app.timezone'))->format('d-m-y à H:i:s');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function notificationMessage(AdminNotification $notification): string
    {
        $name = $this->notificationSenderName($notification);

        return match ($notification->type) {
            'welcome_new_user' => 'Bienvenue sur TalaPlus',
            'media_created' => "{$name} a envoyé une nouvelle vidéo",
            'media_accepted' => 'Votre vidéo a été acceptée',
            'media_rejected' => 'Votre vidéo a été rejetée',
            'media_published' => "{$name} a publié une vidéo",
            'post_sent' => "{$name} a publié une nouvelle publication",
            'product_added' => "{$name} a ajouté un nouveau produit",
            'product_accepted' => 'Votre produit a été accepté',
            'product_rejected' => 'Votre produit a été rejeté',
            'product_ordered' => "{$name} a commandé un produit",
            'comment_sent' => "{$name} a envoyé un commentaire",
            'like_sent' => "{$name} a aimé votre contenu",
            'gift_sent' => "{$name} vous a envoyé un cadeau",
            'report_sent' => "{$name} a signalé un élément",
            'new_follower' => "{$name} vous suit",
            'mention' => "{$name} vous a mentionné",
            'stock_empty' => 'Un stock de produit est vide',
            'payment_pending' => 'Un paiement est en attente',
            'payment_successful' => 'Un paiement a réussi',
            'payment_failed' => 'Un paiement a échoué',
            default => str((string) $notification->type)->replace('_', ' ')->ucfirst()->toString(),
        };
    }

    private function notificationUrl(AdminNotification $notification): ?string
    {
        if ($notification->media_id) {
            return 'https://tempor.silasmas.com/videos/'.$notification->media_id;
        }

        if ($notification->product_id) {
            return 'https://tempor.silasmas.com/products/'.$notification->product_id;
        }

        if ($notification->comment_id) {
            return 'https://tempor.silasmas.com/comments/'.$notification->comment_id;
        }

        return null;
    }

    private function notificationSenderName(AdminNotification $notification): string
    {
        return trim(($notification->fromUser?->firstname ?? '').' '.($notification->fromUser?->lastname ?? '')) ?: 'Un membre';
    }
}
