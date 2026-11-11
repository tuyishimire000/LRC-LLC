<?php
namespace App\Console\Commands;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
class CreateAdminUser extends Command {
 protected $signature='lrc:admin-create';
 protected $description='Create the first LRC super administrator interactively';
 public function handle():int {
  $name=$this->ask('Administrator full name');
  $email=strtolower(trim($this->ask('Administrator email')));
  $password=$this->secret('Password (minimum 12 characters)');
  $confirmation=$this->secret('Confirm password');
  if(!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen((string)$password)<12 || $password!==$confirmation){$this->error('Invalid details or passwords do not match. No account was created.');return self::FAILURE;}
  if(User::where('email',$email)->exists()){$this->error('An account with this email already exists.');return self::FAILURE;}
  $user=User::create(['name'=>$name,'email'=>$email,'password'=>Hash::make($password),'status'=>'active']);
  $role=Role::firstOrCreate(['name'=>'super_admin'],['label'=>'Super administrator']);
  $user->roles()->syncWithoutDetaching([$role->id]);
  $this->info('Super administrator created. Store the credentials securely and enable MFA before production.');
  return self::SUCCESS;
 }
}