<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use GuzzleHttp\Psr7\Response;
use App\Http\Helpers\GetFilesFromDirectory;
use App\Http\Helpers\PdfToText;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use Elasticsearch\ClientBuilder;
use LaravelAcl\Authentication\Models\Content;
use LaravelAcl\Authentication\Models\MarketingAsset;
use LaravelAcl\Authentication\Models\MarketingCategory;
use LaravelAcl\Authentication\Models\MarketingProduct;
use LaravelAcl\Authentication\Models\MarketingVendor;
use LaravelAcl\Authentication\Models\UserUpload;


class SearchController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = \Elasticsearch\ClientBuilder::create()->build();
    }

    //Mapping attachment for docs and pdfs
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function mapping_attachment(){

        $pageTitle = "Full Text Search on PDF / DOCX / TXT";
        $result = array();
        $input = Input::all();

        if(count($input)>0)
        {
            $query= $input['query'];

            $result = $this->ingest_processor_searching($query);

            if($result != null)
            {
                return view('admin::elastic_search.search_page', [
                    'data' => $result,
                    'pageTitle'=> $pageTitle,

                ]);

            }else{
                Session::flash('info', 'No Match Found!');
                return redirect()->back();
            }

        }else{
            return view('admin::elastic_search.search_page', [
                'data' => $result,
                'pageTitle'=> $pageTitle,

            ]);
        }

    }

    //Index all Index
    /**
     * @return Response
     */
    public function set_all_data_indexing()
    {
        // get all setting as pre-defined for ES Engine
        $es_search_settings = DB::table('es_search_settings')->where('status', 'active')->get();

        // if setting data's are available
        if(count($es_search_settings)>0)
        {
            // get table data
            foreach ($es_search_settings as $table)
            {
                //DB::query
                $data = DB::table($table->table_name);

                //conditional_type for the table
                if($table->conditional_type != null)
                {
                    $data = $data->where('type', $table->conditional_type);
                }
                $data = $data->get();

                //check data's are there run the index method
                if(count($data)>0){
                    // got the indexing method

                    $result = $this->set_data_as_index_bulk($data, $table);
                }

            }
        }
        return response('Successfully Indexed in ElasticSearch!');
    }

    // add  data into a index
    /**
     * @param $table_name
     * @param $data_id
     * @return array|Response
     */
    public function add_or_update_data($table_name, $data_id)
    {
        if( $table_name != null && $data_id != null)
        {
            $es_settings_table = DB::table('es_search_settings')->where('table_name', $table_name)->first();
            $es_index_name = $es_settings_table->es_index_name;
            $es_type_name = $es_settings_table->es_type_name;
            $id = $data_id;

            $params = isset($es_settings_table->params_for_url) ? $es_settings_table->params_for_url : null;

            // set data as an array() // $table_name, $data_id
            $data = DB::table($table_name)->where('id', $data_id)->first();

            $parameters = null;
            if($params != null)
            {
                $string_val = $es_settings_table->params_for_url;
                $array= explode( ',', $string_val );


                $parameters = null;
                for ($i = 0; $i < count($array); $i++) {

                    $column_name = trim($array[$i]);
                    $value_of_column = $data->$column_name;

                    if ($value_of_column != null)
                    {
                        $parameters.= $value_of_column."/";
                    }
                }
            }

            // set url ( route + params )
            $url = null;
            if($parameters != null)
            {
                $url = $es_settings_table->route_url."/".$parameters;
            }else{
                $url = $es_settings_table->route_url;
            }

            // params for body
            $es_params['body'][] = [
                'index' => [
                    '_index' => $es_index_name,
                    '_type' => $es_type_name,
                    '_id' => $id,
                    '_routing' => $url,
                ]
            ];

            $es_out = array();
            foreach($data as $key => $value)
            {
                $es_out[$key] = $value;
            }
            $testField = [
                'testField' => @$data->name." ".@$data->description." ".@$data->content_title ,
            ];
            $es_data = array_merge($es_out, $testField);

            $es_params['body'][] = $es_data;

            $responses = $this->client->bulk($es_params);

            return $responses;

        }else{

            return response('Missing Data !');

        }



    }


    // Delete single index data
    /**
     * @param $es_index_name
     * @param $es_type_name
     * @param $table_id
     * @return Response
     */
    public function delete_single_data_index($es_index_name, $es_type_name, $table_id)
    {
        $client = $this->client;

        if($es_index_name != null && $es_type_name != null && $table_id !=null)
        {
            $params = [
                '_index' => $es_index_name,
                '_type' => $es_type_name,
                '_id' => $table_id,
            ];
            $client->delete($params);
        }
        return response('Successfully Deleted!');
    }

    // Delete type or index wise data
    /**
     * @param $es_settings_data
     * @return Response
     */
    public function delete_es_data_per_settings($es_settings_data)
    {
        $client = $this->client;

        // check if settings are available
        if(count($es_settings_data)>0)
        {
            foreach ($es_settings_data as $val)
            {
                //table_name , index_name, type_name
                $table_name = $val->table_name;
                $es_index_name = $val->table_name;
                $es_type_name = $val->table_name;

                // if all data(s) are exists
                if($table_name != null && $es_index_name != null && $es_type_name != null)
                {
                    //DB::query
                    $data = DB::table($table_name)->get();
                    if(count($data)>0)
                    {
                        foreach ($data as $value)
                        {
                            $params = [
                                '_index' => $es_index_name,
                                '_type' => $es_type_name,
                                '_id' => $value->id,
                            ];
                            $client->delete($params);
                        }
                    }
                }
            }
        }
        return response('Successfully Deleted!');
    }


    /**
     * @param $data
     * @param $es_settings_table
     * @return mixed
     */
    protected function set_data_as_index_bulk($data, $es_settings_table)
    {

        $index_name = isset($es_settings_table->es_index_name)?$es_settings_table->es_index_name:null;
        $type_name = isset($es_settings_table->es_type_name)?$es_settings_table->es_type_name:null;

        if(count($data)>0 && $index_name != null && $type_name != null)
        {
            foreach($data as $val)
            {
                // set parameters according to es_settings_table
                $params = isset($es_settings_table->params_for_url) ? $es_settings_table->params_for_url : null;

                $parameters = null;
                if($params != null)
                {
                    $string_val = $es_settings_table->params_for_url;
                    $array= explode( ',', $string_val );

                    $parameters = null;
                    for ($i = 0; $i < count($array); $i++) {

                        $column_name = trim($array[$i]);
                        $value_of_column = isset($val->$column_name)?$val->$column_name:null;
                        if ($value_of_column != null)
                        {
                            $parameters.= $value_of_column."/";
                        }

                    }
                }

                // set url ( route + params )
                $url = null;
                if($parameters != null)
                {
                    $url = $es_settings_table->route_url."/".$parameters;
                }else{
                    $url = $es_settings_table->route_url;
                }

                // params for body
                $es_params['body'][] = [
                    'index' => [
                        '_index' => $es_settings_table->es_index_name,
                        '_type' => $es_settings_table->es_type_name,
                        '_id' => $val->id,
                        '_routing' => $url,
                    ]
                ];

                // set data as an array()
                $es_out = array();
                foreach($val as $key => $value)
                {
                    $es_out[$key] = $value;
                }
                $testField = [
                    'testField' => @$val->name." ".@$val->description." ".@$val->content_title ,
                ];
                $es_data = array_merge($es_out, $testField);

                $es_params['body'][] = $es_data;

            }

            $responses = $this->client->bulk($es_params);

            return $responses;
        }else{
            return response("Missing Params! Please check your settings !");
        }

    }



    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function es_search()
    {

        $pageTitle = "Search result";
        $results = array();
        $input = Input::all();

        if (count($input) > 0)
        {
            $query = $input['query'];
            $category = isset($input['category'])?$input['category']:null;
            $results = $this->elastic_search($query, $category);
        }

        return view('admin.elastic_search.search_page',
        [
            'search_results' => $results,
            'pageTitle' => $pageTitle,
        ]);
    }


    /**
     * @param $query
     * @param null $category
     * @return array|null
     */
    protected function elastic_search($query, $category=null)
    {
        if(isset($query))
        {
            $es_settings_table = DB::table('es_search_settings');
            if($category != null)
            {
                $es_settings_table = $es_settings_table->where('es_index_name', $category);
            }
            $es_settings_table = $es_settings_table->get();

            //set response as null array
            $response = array();
            foreach ($es_settings_table as $item)
            {
                $params = [
                    'index' => isset($item->es_index_name)?$item->es_index_name:null,
                    'type' => isset($item->es_type_name)?$item->es_type_name:null,
                    'body' => [
                        'query' => [
                            'match' => [
                                'testField' => isset($query)?$query:null,
                            ]
                        ],
                    ],
                ];
                $result = $this->client->search($params);

                if(count($result)>0)
                {
                    if (count($result['hits']['hits'])>0)
                    {
                        $response [] = $result['hits']['hits'];
                    }

                }
            }

            return $response;

        }else{

            return null;
        }
    }


    /**
     * @param $es_search_settings_id
     * @return mixed
     */
    public function set_index_es($es_search_settings_id)
    {
        $es_sertings_table = DB::table('es_search_settings')->where('id', $es_search_settings_id)->first();

        if(isset($es_sertings_table->table_name) && $es_sertings_table->table_name != null)
        {
            $data = DB::table($es_sertings_table->table_name)->get();
            $this->set_data_as_index_bulk($data, $es_sertings_table);
        }

        return Redirect::back()->with("Successfully Indexed !");

    }

    /**
     * @param $es_search_settings_id
     * @return mixed
     */
    public function delete_index_es($es_search_settings_id)
    {
        $es_sertings_table = DB::table('es_search_settings')->where('id', $es_search_settings_id)->first();
        $es_index_name = $es_sertings_table->es_index_name;
        $es_type_name = $es_sertings_table->es_type_name;

        $table_name = $es_sertings_table->table_name;

        if($table_name != null)
        {
            $data = DB::table($table_name)->get();
            if (count($data)>0)
            {
                foreach ($data as $item)
                {
                    $id = $item->id;
                    if($id != null)
                    {
                        $result = $this->delete_single_data_index($es_index_name, $es_type_name, $id);
                    }
                }
            }
        }

        return Redirect::back()->with("Successfully Deleted !");
    }


    /**
     * @param $table_name
     * @param $data_id
     * @return mixed
     */
    public function api_add_update_to_es($table_name, $data_id)
    {
        // table_name must be table name and data_id is id from the table
        if($table_name != null && $data_id !=null)
        {
            $result = $this->add_or_update_data($table_name, $data_id);
        }

        return Redirect::back()->with("Success !");

    }




}
