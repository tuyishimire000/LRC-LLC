<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class User extends Authenticatable {
 use HasFactory,Notifiable;
 protected $fillable=['name','email','password','phone','status'];
 protected $hidden=['password','remember_token'];
 protected function casts():array{return ['email_verified_at'=>'datetime','password'=>'hashed'];}
 public function roles():BelongsToMany{return $this->belongsToMany(Role::class);}
 public function hasAnyRole(array $roles):bool{return $this->roles()->whereIn('name',$roles)->exists();}
}