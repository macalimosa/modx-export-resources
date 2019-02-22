<?php
ini_set('max_execution_time', 300);
$debug = $modx->getOption('debug',$scriptProperties, false);
$filename = $modx->getOption('filename',$scriptProperties, 'export');
$resClass = $modx->getOption('ressClass',$scriptProperties, 'modDocument');
$parent = $modx->getOption('parent',$scriptProperties, null);
$delimiter = $modx->getOption('delimter',$scriptProperties, ","); //csv delimeter
$except_properties = $modx->getOption('exceptProperties',$scriptProperties, null); // comma delimeted
$except_tvs = $modx->getOption('exceptTvs',$scriptProperties, []); //tv names
$except_properties_param = [];
$limit = 0; //for debug
if($except_properties){
    $except_properties_param = explode(",",$except_properties);
}
$except_default_properties = array_merge(array(
    'type','contentType','link_attributes',
    'pub_date','unpub_date','menuindex',
    'searchable','cacheable','createdby',
    'editedby','editedon',
    'deleted','deletedon','deletedby',
    'publishedby','donthit',
    'privateweb','privatemgr','content_dispo',
    'context_key','content_type',
    'hide_children_in_tree','show_in_tree','properties'
    
),$except_properties_param);

if(!$parent){
    return 'Parent is required!!!';
}
$document = $modx->getObject('modResource',array(
    'id' => $parent,
));
if(!$document){
    return 'Parent Not Found!';
}
if(!$debug){
    header("Content-type: application/csv");
    header("Content-Disposition: attachment; filename=".$document->get('pagetitle') . ".csv");
}


// Query Resources
$children =  $modx->getChildIds($parent,10,array('context' => 'web'));
$c = $modx->newQuery('modResource');
$c->where(array('id:IN'=>$children,'isfolder' => '0')); //include the parent
// if($children){
//     $c->where(array('id:IN'=>$children,'isfolder' => "1"),xPDOQuery::SQL_OR);
// }
$resources = $modx->getCollection('modResource',$c);
// Create a "template" Resource
$template = $modx->newObject($resClass);
$template = $template->toArray();
$idx = 0;
// Open a file handle
$file = fopen('php://output', 'w');

$data = [];
$data_temp = [];
$temps = [];
$headers = [];
$headers1 = [];
foreach ($resources as $res) {
    // get resource fields
    $id = $res->get('id');
    $fields = $res->toArray();
    $fields = array_merge($template, $fields);
    $fields = array_diff_key($fields, array_flip($except_default_properties)); //remove element by keys
    $temp_fields = $fields;
    // get tvs from resource
    $tvs = $res->getMany('TemplateVars');
    if($idx === 0){
        $headers = array_keys($fields);
    }
    $tvNames = [];
    foreach ($tvs as $tv) {
        $tv_id = 'tv'. $tv->get('id');
        $tvs_array[] = ['value' =>$tv->renderOutput($id) ,'caption' => $tv->get('caption'),'id' => $tv_id];
    }

    foreach($fields as $key => $value){
        foreach($tvs_array as $tv){
            if($key == $tv['id']){
                $fields[$key] = $tv['value']; //append values from existing key
            }else{
                $fields[$tv['id']] = $tv['value']; // create new keys
                if(isset($tv['caption'])){
                    $headers1[$tv['id']] = $tv['caption']; //make first header
                }
            }
        }
        
    }
    $header = [];
    $data[] = $fields;
    $idx++;

}

$counter = 0;

$first_header = array_merge($headers,array_values($headers1));
array_unshift($data,array_keys(end($data)));
array_unshift($data,$first_header);

foreach ($data as $row){
    fputcsv($file, $row,$delimiter);
    // close file handler
    if($limit){
        if($counter >= $limit){
            break;
            if (!fclose($file)) echo 'problem closing file';
        }
        $counter++;
    }
   
}

if (!fclose($file)) echo 'problem closing file';
exit();
