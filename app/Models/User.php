<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    protected $fillable = [
        'name', 'email', 'password',
        'phone', 'job_title', 'department', 'employee_id',
        'location', 'timezone', 'language', 'bio', 'linkedin_url',
        'profile_photo_path', 'signature_path',
        'date_of_joining', 'emergency_contact_name', 'emergency_contact_phone',
        'status', 'two_factor_enabled', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'date_of_joining'    => 'date',
            'last_login_at'      => 'datetime',
            'two_factor_enabled' => 'boolean',
        ];
    }

    /**
     * Get the full URL to the user's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo_path && file_exists(storage_path('app/public/' . $this->profile_photo_path))) {
            return asset('storage/' . $this->profile_photo_path);
        }
        return '';
    }

    /**
     * Get the full URL to the user's signature image.
     */
    public function getSignatureUrlAttribute(): string
    {
        if ($this->signature_path && file_exists(storage_path('app/public/' . $this->signature_path))) {
            return asset('storage/' . $this->signature_path);
        }
        return '';
    }

    /**
     * Get user initials from name (up to 2 letters).
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', trim($this->name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }
}
