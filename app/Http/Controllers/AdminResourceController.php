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
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Reason;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'fields' => [
                ['name' => 'pricing_name', 'label' => 'Nom de la tarification', 'type' => 'translatable', 'required' => true],
                ['name' => 'pricing_type', 'label' => 'Type de tarification', 'type' => 'select', 'options' => ['money' => 'Montant fixe', 'percentage' => 'Pourcentage'], 'required' => true],
                ['name' => 'reason', 'label' => 'Motif', 'type' => 'select', 'options' => ['' => '-', 'media_boost' => 'Boost de vidéo', 'ad' => 'Publicité', 'gift_sent' => 'Cadeau envoyé', 'user_certfied' => 'Certification utilisateur']],
                ['name' => 'pricing_cost', 'label' => 'Coût', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'currency', 'label' => 'Devise', 'type' => 'text'],
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
            'fields' => [
                ['name' => 'media_title', 'label' => 'Titre de la vidéo', 'type' => 'translatable'],
                ['name' => 'media_description', 'label' => 'Description', 'type' => 'translatable-textarea'],
                ['name' => 'media_url', 'label' => 'URL de la vidéo', 'type' => 'text'],
                ['name' => 'cover_url', 'label' => 'URL de la couverture', 'type' => 'text'],
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
                    'crafts_diy' => 'Bricolage et DIY',
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
                ['name' => 'avatar_url', 'label' => 'URL avatar', 'type' => 'text'],
                ['name' => 'cover_url', 'label' => 'URL couverture', 'type' => 'text'],
                ['name' => 'promo_code', 'label' => 'Code promotionnel', 'type' => 'text'],
                ['name' => 'tips_at_every_login', 'label' => 'Conseils à chaque connexion', 'type' => 'checkbox'],
                ['name' => 'is_online', 'label' => 'En ligne', 'type' => 'checkbox'],
                ['name' => 'christian_preference', 'label' => 'Préférence chrétienne', 'type' => 'checkbox'],
                ['name' => 'status', 'label' => 'Statut', 'type' => 'select', 'options' => ['created' => 'Créé', 'activated' => 'Activé', 'disabled' => 'Désactivé', 'blocked' => 'Bloqué', 'deleted' => 'Supprimé']],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['uncertified' => 'Non certifié', 'certified' => 'Certifié']],
            ],
            'columns' => ['firstname', 'lastname', 'email', 'phone', 'status'],
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
        'crafts_diy' => 'Bricolage et DIY',
        'sports' => 'Sports',
        'documentary' => 'Documentaire',
        'product' => 'Produit',
        'service' => 'Service',
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

        return response()->json(
            $config['model']::query()
                ->when(! empty($config['with']), fn ($query) => $query->with($config['with']))
                ->when(! empty($config['where']), fn ($query) => $query->where($config['where']))
                ->when(! empty($config['current_user_only']), fn ($query) => $query->where('to_user_id', request()->user()?->id))
                ->findOrFail($id)
        );
    }

    public function store(Request $request, string $resource)
    {
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);

        $item = DB::transaction(function () use ($request, $config): Model {
            $item = new $config['model'];
            $item->fill($this->payload($request, $config, $item));
            $item->save();
            $this->saveChildren($request, $config, $item);
            $this->saveFiles($request, $config, $item);

            return $item;
        });

        return response()->json(['message' => __('admin.saved'), 'item' => $this->present($item, $config)]);
    }

    public function update(Request $request, string|int $resource, string|int|null $id = null)
    {
        [$resource, $id] = $this->routeArguments($resource, $id);
        $config = $this->config($resource);
        abort_if($config['readonly'] ?? false, 403);

        $item = DB::transaction(function () use ($request, $config, $id): Model {
            $item = $config['model']::findOrFail($id);
            $item->fill($this->payload($request, $config, $item));
            $item->save();
            $this->saveChildren($request, $config, $item);
            $this->saveFiles($request, $config, $item);

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

    private function config(string $resource): array
    {
        abort_unless(isset($this->resources[$resource]), 404);

        return $this->resources[$resource];
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

        return $payload;
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
                'file_url' => Storage::disk('public')->url($uploadedFile->store('app-infos', 'public')),
                'file_description' => $item->comment_content,
                'file_type' => 'photo',
                'user_id' => $item->user_id,
                'comment_id' => $item->id,
            ]);
        }
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

            if ($this->isDateTimeColumn($column)) {
                $raw[$column.'_display'] = $this->dateTimeDisplay($value);

                continue;
            }

            if (is_array($value)) {
                $raw[$column.'_display'] = $value[$locale] ?? $value['fr'] ?? reset($value);
            } else {
                $decoded = is_string($value) ? json_decode($value, true) : null;
                $raw[$column.'_display'] = is_array($decoded) ? ($decoded[$locale] ?? $decoded['fr'] ?? reset($decoded)) : $value;
            }
        }

        foreach ($raw as $column => $value) {
            if ($this->isDateTimeColumn((string) $column) && ! array_key_exists($column.'_display', $raw)) {
                $raw[$column.'_display'] = $this->dateTimeDisplay($value);
            }
        }

        return $raw;
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
