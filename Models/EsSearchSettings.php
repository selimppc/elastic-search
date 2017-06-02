<?php
/**
 * Created by PhpStorm.
 * User: selimreza
 * Date: 2/9/17
 * Time: 2:20 PM
 */

namespace LaravelAcl\Authentication\Models;
namespace App;

use Illuminate\Database\Eloquent\Model;



class EsSearchSettings extends Model
{
    protected $table = 'es_search_settings';

    protected $fillable = [
        'table_name',
        'es_index_name',
        'es_type_name',
        'search_columns',
        'conditional_type',
        'route_url',
        'params_for_url',
        'status',
    ];




}