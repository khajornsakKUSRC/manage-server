<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    protected $fillable = ['name', 'ip'];

    public function vms()
    {
        return $this->hasMany(Vm::class);
    }
}
