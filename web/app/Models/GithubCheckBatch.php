<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubCheckBatch extends Model
{
    protected $table = 'github_check_batches';

    protected $fillable = [
        'admin_id',
        'total_accounts',
        'checked_count',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function results()
    {
        return $this->hasMany(GithubCheckResult::class, 'batch_id');
    }

    public function approvedCount()
    {
        return $this->results()->where('result', 'approved')->count();
    }

    public function notApprovedCount()
    {
        return $this->results()->where('result', 'not_approved')->count();
    }

    public function suspendedCount()
    {
        return $this->results()->where('result', 'suspended')->count();
    }

    public function errorCount()
    {
        return $this->results()->where('result', 'error')->count();
    }
}
