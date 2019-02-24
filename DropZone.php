<?php

namespace devgroup\dropzone;

use Yii;
use yii\base\Widget;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

class DropZone extends Widget
{
    public $model;
    public $attribute;
    public $htmlOptions = [];
    public $name;
    public $options = [];
    public $eventHandlers = [];
    public $url;
    public $removeUrl;
    public $storedFiles = [];
    public $sortable = false;
    public $sortableOptions = [];
    public $message;
    public $messageOptions = [];
    public $createThumb;

    protected $dropzoneName = 'dropzone';

    public function init()
    {
        parent::init();
        Html::addCssClass($this->htmlOptions, 'dropzone');
        Html::addCssClass($this->messageOptions, 'dz-message');
        $this->dropzoneName = 'dropzone_' . $this->id;

        if(empty($this->name) && (!empty($this->model) && !empty($this->attribute))){
            $this->name = Html::getInputName($this->model, $this->attribute);
        }

        if(empty($this->url)){
            $this->url = Url::toRoute(['site/upload']);
        }
        $this->createThumb = isset($this->options['createImageThumbnails'])?$this->options['createImageThumbnails']:true;
    }

    private function registerAssets()
    {
        DropZoneAsset::register($this->getView());
        $this->getView()->registerJs('Dropzone.autoDiscover = false;');
        $this->getView()->registerJs("
            function createHiddenInput" . $this->dropzoneName . "(file ,name , value){
                var multiple = '';
                if(" . $this->dropzoneName . ".options.maxFiles > 1)
                    multiple = '[]'; 
                file._hiddenField = Dropzone.createElement(\"<input type='hidden' name='\"+name+multiple+\"' value='\"+value+\"'>\");
                file.previewElement.appendChild(file._hiddenField);
            }
        ");
    }

    protected function addFiles($files = [])
    {
        // old version
//        $this->view->registerJs('var files = ' . Json::encode($files));
//        $this->view->registerJs('for (var i=0; i<files.length; i++) {
//            ' . $this->dropzoneName . '.emit("addedfile", files[i]);
//            ' . $this->dropzoneName . '.emit("thumbnail", files[i], files[i]["thumbnail"]);
//            ' . $this->dropzoneName . '.emit("complete", files[i]);
//        }');

        // rahbod version
        $thS = '';
        if($this->createThumb)
            $thS = 'if($.inArray(value.name.split(\'.\').pop(), extArr) > -1)
                    {
                        ' . $this->dropzoneName . '.createThumbnailFromUrl(mockFile , value.src);
                        ' . $this->dropzoneName . '.options.thumbnail.call(' . $this->dropzoneName . ', mockFile, value.src);
                    }';
        $this->view->registerJs('
            var extArr = ["jpg","jpeg","png","bmp","gif"];
            var data = ' . Json::encode($files) . ';');
        $this->view->registerJs('
            $.each(data, function(key,value){
                var mockFile = { name: value.name, size: value.size ,serverName : value.name, src : value.src ,accepted : true};
                if ((' . $this->dropzoneName . '.options.maxFiles != null) && ' . $this->dropzoneName . '.getAcceptedFiles().length < ' . $this->dropzoneName . '.options.maxFiles) {
                    ' . $this->dropzoneName . '.emit("addedfile", mockFile);
                    '.$thS.'
                    ' . $this->dropzoneName . '.emit("complete", mockFile);
                    ' . $this->dropzoneName . '.files.push(mockFile);
                    createHiddenInput' . $this->dropzoneName . '(mockFile ,"' . $this->name . '", value.name);
                }
            });');
    }

    protected function decrementMaxFiles($num)
    {
        $this->getView()->registerJs(
            'if (' . $this->dropzoneName . '.options.maxFiles > 0) { '
            . $this->dropzoneName . '.options.maxFiles = '
            . $this->dropzoneName . '.options.maxFiles - ' . $num . ';'
            . ' }'
        );
    }

    protected function createDropzone()
    {
        $options = Json::encode($this->options);
        $this->getView()->registerJs('var '.$this->dropzoneName . ' = new Dropzone("#' . $this->id . '", ' . $options . ');',View::POS_READY, 'dropzone_script_'.$this->dropzoneName);
    }

    public function run()
    {
        $options = [
            'url' => $this->url,
            'paramName' => $this->name,
            'params' => [],
        ];

        if(Yii::$app->request->enableCsrfValidation){
            $options['params'][Yii::$app->request->csrfParam] = Yii::$app->request->getCsrfToken();
        }

        if(!empty($this->message)){
            $message = Html::tag('div', $this->message, $this->messageOptions);
        }else{
            $message = '';
        }

        $this->htmlOptions['id'] = $this->id;
        $this->options = ArrayHelper::merge($this->options, $options);
        echo Html::tag('div', $message, $this->htmlOptions);

        $this->registerAssets();

        $this->createDropzone();

        foreach($this->eventHandlers as $event => $handler){
            $handler = new \yii\web\JsExpression($handler);
            $this->getView()->registerJs(
                $this->dropzoneName . ".on('{$event}', {$handler})"
            );
        }

        if($this->removeUrl){
            $this->getView()->registerJs(
                $this->dropzoneName . ".on('removedfile', function(file) {   
                    jQuery.ajax({
                        url: '" . $this->removeUrl . "',
                        data:{fileName : file.serverName},
                        type : \"POST\",
                        dataType : \"json\"
                    });
                })"
            );
        }

        $this->getView()->registerJs(
            $this->dropzoneName . '.on("success", function(file, result) {
                var responseObj = JSON.parse(result);
                if(responseObj.status)
                    file.serverName = responseObj.filename;
                else
                    this.removeFile(file);
                if(file.serverName)
                    createHiddenInput' . $this->dropzoneName . '(file ,"' . $this->name . '",file.serverName);
            })'
        );

        if($this->storedFiles instanceof UploadedFiles){
            $this->addFiles($this->storedFiles->getFiles());
//            $this->decrementMaxFiles(count($this->storedFiles->getFiles()));
        }else{
            $this->addFiles($this->storedFiles);
//            $this->decrementMaxFiles(count($this->storedFiles));
        }

        if($this->sortable){
            $options = Json::encode($this->sortableOptions);
            $this->getView()->registerJs("jQuery('#{$this->id}').sortable(" . $options . ");");
        }
    }
}