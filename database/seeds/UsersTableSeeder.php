<?php

use Illuminate\Database\Seeder;
use Faker\Factory;
class UsersTableSeeder extends Seeder
{
    
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //$faker=Factory::create();

       $admin = \App\User::create([
        'name' => 'admin',
        'role'     =>'admin',
        'email' => 'admin@gmail.com',
        'password' => bcrypt('admin@123456'),
        'email_verified_at' => \Carbon\Carbon::now(),
       
       
       ]);
}


}