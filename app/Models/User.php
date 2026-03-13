<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'contact_number',
        'address',
        'is_active',
        'stripe_customer_id',
        'google_id',
        'otp',
        'otp_expires_at',
        'otp_verified',
        'otp_verified_at',
        'email_verified_at',
        'gender',
        'ban_type',
        'ban_expires_at',
        'ban_reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'total_orders',
        'total_food_delivery_orders',
        'total_ferry_drop_orders',
        'total_tasks_created',
        'runner_orders_completed',
        'runner_orders_pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ban_expires_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'otp_verified' => 'boolean',
            'otp_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function getAvatarAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        } else {
            return asset('images/user/default.jpg');
        }
    }

    public function runner()
    {
        return $this->hasOne(Runner::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function supportMessages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function repliedSupportMessages()
    {
        return $this->hasMany(SupportMessage::class, 'replied_by');
    }

    public function getTotalOrdersAttribute()
    {
        return $this->orders()->count();
    }

    public function getTotalFoodDeliveryOrdersAttribute()
    {
        return $this->orders()->where('type', 'food_delivery')->count();
    }

    public function getTotalFerryDropOrdersAttribute()
    {
        return $this->orders()->where('type', 'ferry_drops')->count();
    }

    public function getTotalTasksCreatedAttribute()
    {
        return TaskService::where('user_id', $this->id)->count();
    }

    public function getRunnerOrdersCompletedAttribute()
    {
        return TaskService::where('runner_id', $this->id)->where('status', 'completed')->count();
    }

    public function getRunnerOrdersPendingAttribute()
    {
        return TaskService::where('runner_id', $this->id)->whereIn('status', ['new', 'pending'])->count();
    }
}
