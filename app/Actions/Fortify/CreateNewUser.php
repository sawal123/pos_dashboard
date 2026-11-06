<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'password_login_enabled' => true,
            ]);

            $rawBusinessName = ! empty($input['name'])
                ? trim($input['name']).' Store'
                : 'Toko Saya';

            $slugBase = Str::slug($rawBusinessName);
            if (empty($slugBase)) {
                $slugBase = 'toko';
            }
            $slug = $slugBase.'-'.Str::lower(Str::random(6));

            $business = Business::create([
                'name' => $rawBusinessName,
                'slug' => $slug,
                'status' => 'active',
                'business_type' => 'retail',
            ]);

            $user->businesses()->attach($business->id, [
                'role' => Business::ROLE_OWNER,
            ]);

            Outlet::create([
                'business_id' => $business->id,
                'name' => 'Outlet Utama',
                'code' => 'OUT-1',
                'status' => 'active',
            ]);

            Subscription::create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
            ]);

            return $user;
        });
    }
}
