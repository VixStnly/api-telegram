<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReplyRuleGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'auto_reply_rule_id',
        'group_key',
    ];

    public function rule()
    {
        return $this->belongsTo(AutoReplyRule::class, 'auto_reply_rule_id');
    }
}