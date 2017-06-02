<?php
/**
 * Created by PhpStorm.
 * User: selimreza
 * Date: 1/23/17
 * Time: 2:29 PM
 */

/*
 *  for the controller Search Controller
 */
Route::group(['middleware' => ['logged', 'can_see', 'redirect_url']], function() {

    Route::get('mapping_attachment', [
        'as' => 'mapping_attachment',
        'uses' => 'SearchController@mapping_attachment'
    ]);

    Route::get('set_all_data_indexing', [
        'as' => 'set_all_data_indexing',
        'uses' => 'SearchController@set_all_data_indexing'
    ]);

    Route::get('add_or_update_data/{table_name}/{data_id}', [
        'as' => 'add_or_update_data',
        'uses' => 'SearchController@add_or_update_data'
    ]);

    Route::get('delete_single_data_index/{es_index_name}/{es_type_name}/{table_id}', [
        'as' => 'delete_single_data_index',
        'uses' => 'SearchController@delete_single_data_index'
    ]);

    Route::get('delete_es_data_per_settings/{es_settings_data}', [
        'as' => 'delete_es_data_per_settings',
        'uses' => 'SearchController@delete_es_data_per_settings'
    ]);


    Route::get('es_search', [
        'as' => 'es_search',
        'uses' => 'SearchController@es_search'
    ]);



    Route::get('elastic_search/{query}/{category}', [
        'as' => 'elastic_search',
        'uses' => 'SearchController@elastic_search'
    ]);



    /*
     * Setting Page for ES
     */

    Route::get('admin/es-settings', [
        'as' => 'admin/es-settings',
        'uses' => 'ElasticSearchController@index'
    ]);

    Route::get('admin/edit-es-settings/{id}', [
        'as' => 'admin/edit-es-settings',
        'uses' => 'ElasticSearchController@edit'
    ]);

    Route::any('admin/update-es-settings/{id}', [
        'as' => 'admin/update-es-settings',
        'uses' => 'ElasticSearchController@update'
    ]);

    Route::any('admin/set-index-es/{es_search_settings_id}', [
        'as' => 'admin/set-index-es',
        'uses' => 'SearchController@set_index_es'
    ]);

    Route::any('admin/delete-index-es/{es_search_settings_id}', [
        'as' => 'admin/delete-index-es',
        'uses' => 'SearchController@delete_index_es'
    ]);

    Route::any('admin/api-add-update-to-es/{table_name}/{data_id}', [
        'as' => 'admin/api-add-update-to-es',
        'uses' => 'SearchController@api_add_update_to_es'
    ]);



});



