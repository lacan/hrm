<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc.php");
require_once("./inc/Fileserver.inc.php");

session_start();

if (isset($_GET['home'])) {
  header("Location: " . "home.php"); exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (isset($_SESSION['jobcreated'])) {
  unset($_SESSION['jobcreated']);
}

if (!isset($_SESSION['fileserver'])) {
  # session_register("fileserver");
  $name = $_SESSION['user']->name();
  $_SESSION['fileserver'] = new Fileserver($name);
}

if (!isset($_SESSION[ 'parametersetting' ])) {
    $_SESSION[ 'parametersetting' ] = new ParameterSetting();
}
$fileFormat = $_SESSION[ 'parametersetting' ]->parameter("ImageFileFormat");

$message = "";
if (isset($_POST['down'])) {
    if (isset($_POST['userfiles']) && is_array($_POST['userfiles'])) {
        $_SESSION['fileserver']->addFilesToSelection($_POST['userfiles']);
    }
    if (isset($_POST['ImageFileFormat']) && !empty($_POST['ImageFileFormat'])) {
        $_SESSION[ 'parametersetting' ]->checkPostedImageParameters( $_POST );
    }
}
else if (isset($_POST['up'])) {		
  if (isset($_POST['selectedfiles']) && is_array($_POST['selectedfiles'])) {
    $_SESSION['fileserver']->removeFilesFromSelection($_POST['selectedfiles']);
  }  
}
else if (isset($_POST['update'])) {
  $_SESSION['fileserver']->resetFiles();
}
else if (isset($_POST['OK'])) {

    if (!$_SESSION['fileserver']->hasSelection()) {
        $message = "Please add at least one image to your selection";
    }
    else {
        if (isset($_POST['ImageFileFormat']) && !empty($_POST['ImageFileFormat'])) {
            $_SESSION[ 'parametersetting' ]->checkPostedImageParameters( $_POST );
        }
        header("Location: " . "select_parameter_settings.php"); exit();
    }
}

$script = array( "settings.js", "ajax_utils.js" );

// All the user's files in the server.
$files = $_SESSION['fileserver']->files();

// display only relevant files.
if ($files != null) {

    $generatedScript = "
function storeFileFormatSelection(sel,series) {
  
   // Get current selection
   var format = $('#' + sel.id + ' :selected').attr(\"name\");
   
   // Store it
   ajaxSetFileFormat(format);
   
   // Now filter by type
   filterImages(sel,series);
};
";
  
    $generatedScript .= "
function filterImages (extension,series) {

    var selectObject = document.getElementById(\"selectedimages\");
    if (selectObject.length >= 0) {
        for (i = selectObject.length - 1; i>=0; i--) {
            selectObject.remove(selectObject.length - 1);
        }
    }

    var selectObject = document.getElementById(\"filesPerExtension\");
    if (selectObject.length > 0) {
        for (i = selectObject.length - 1; i>=0; i--) {
            selectObject.remove(selectObject.length - 1);
        }
    }

    var selectedExtension = extension.options[extension.selectedIndex].value;

    var autoseries = document.getElementById(\"series\");
";

        /* For each file, create javascript code for when the file
         belongs to a series and for when it doesn't. */
    $condensedSeries = $_SESSION['fileserver']->condenseSeries();

    foreach ($files as $key => $file) {
        
        if ($_SESSION['fileserver']->isPartOfFileSeries($file)) {

            $generatedScript .= "

              // Automatically load file series. 
              if(autoseries.checked) {
              ";

            if (in_array($file,$condensedSeries)) {
                $generatedScript .= "
                    var selectItem = document.createElement('option');
                    selectItem.text = \"$file\";
                    selectObject.add(selectItem,null);
                    ";
            }
            $generatedScript .= "     

              } else {

                  // Do not load file series automatically.    
                  if(getExtension(\"$file\") == selectedExtension) {
                     var selectItem = document.createElement('option');
                     selectItem.text = \"$file\";
                     selectObject.add(selectItem,null);
                  }
              }
              ";
                
        } else {
            $generatedScript .= "

               // File does not belong to a file series. 
               if(getExtension(\"$file\") == selectedExtension) {
                   var selectItem = document.createElement('option');
                   selectItem.text = \"$file\";
                   selectObject.add(selectItem,null);
               }
               ";
        }
        
    }

    $generatedScript .= "

}

function imageAction (list) {

    var n = list.selectedIndex;     // Which item is the first selected one

    if( undefined === window.lastSelectedImgs ){
        window.lastSelectedImgs = [];
        window.lastSelectedImgsKey = [];
        window.lastShownIndex = -1;
    }

    var snew = 0;

    var count = 0;
    for (i=0; i<list.options.length; i++) {
        if (list.options[i].selected) {
            if( undefined === window.lastSelectedImgsKey[i] ){
                // New selected item
                snew = 1;
                n = i;
            }
            count++;
        }
    }

    if (snew == 0) {
        // deselected image
        for (i=0; i<window.lastSelectedImgs.length; i++) {
            key = window.lastSelectedImgs[i];
            if ( !list.options[key].selected ) {
                snew = -1
                    n = key;
            }
        }
    }

    window.lastSelectedImgs = [];
    window.lastSelectedImgsKey = [];
    count = 0;
    for (i=0; i<list.options.length; i++) {
        if (list.options[i].selected) {
            window.lastSelectedImgs[count] = i;
            window.lastSelectedImgsKey[i] = true;
            count++;
        }
    }

    if (count == 0 ) {
        window.previewSelected = -1;
    }

    var val = list[n].value;

    if ( n == window.lastShownIndex ) {
        return
    }
    window.lastShownIndex = n;

    switch ( val )
    {
";

    foreach ($files as $key => $file) {
        $generatedScript .= "
        case \"$file\" :
            ". $_SESSION['fileserver']->getImageAction($file,
                $key, "src", "preview", 0, 1). "
            break;
            ";
    }


    $generatedScript .= "
    }
}
";
}

include("header.inc.php");

$info = " <h3>Quick help</h3> <p>In this step, you can select the files " .
    "from the list of available images that will be restored.</p><p>You " .
    "can use SHIFT- and " .
    "CTRL-click to select multiple files.</p> <p>Click on a file name in " .
    "any of the fields to get a preview.</p>";
 

?>

    <!--
      Tooltips
    -->
    <span class="toolTip" id="ttSpanDown">
        Add files to the list of selected images.
    </span>
    <span class="toolTip"  id="ttSpanUp">
        Remove files from the list of selected images.
    </span>
    <span class="toolTip"  id="ttSpanRefresh">
        Refresh the list of available images on the server.
    </span>
    <span class="toolTip"  id="ttSpanForward">
        Continue to step 2/4 - Image parameters
    </span>

    <div id="nav">
        <ul>
            <li>
                <img src="images/user.png" alt="user" />
                &nbsp;<?php echo $_SESSION['user']->name(); ?>
            </li>
            <?php
            if ( !$_SESSION['user']->isAdmin()) {
            ?>
            <li><a href="file_manager.php">
                    <img src="images/filemanager_small.png" alt="file manager" />
                    &nbsp;File manager
                </a>
            </li>
            <?php
            }
            ?>
            <li>
                <a href="<?php echo getThisPageName();?>?home=home">
                    <img src="images/home.png" alt="home" />&nbsp;Home
                </a>
            </li>
            <li>
                <a href="javascript:openWindow(
                   'http://www.svi.nl/HuygensRemoteManagerHelpSelectImages')">
                    <img src="images/help.png" alt="help" />&nbsp;Help
                </a>
            </li>
        </ul>
    </div>
    
    <div id="content">
        <h3><img alt="SelectImages" src="./images/select_images.png" width="40"/> &nbsp;Step 1/5 - Select images</h3>
        
                    <form method="post" action="" id="fileformat">
                    <fieldset class="setting"
                onmouseover="javascript:changeQuickHelp( 'format' );" >
            
                <legend>
                    <a href="javascript:openWindow(
                       'http://www.svi.nl/FileFormats')">
                        <img src="images/help.png" alt="?" />
                    </a>
                    Image file format
                </legend>
                    
                    <select name="ImageFileFormat" id="ImageFileFormat"
                     size="1"
                     onclick="javascript:storeFileFormatSelection(this,series)"
                     onchange="javascript:storeFileFormatSelection(this,series)"
                     onkeyup="this.blur();this.focus();" >

<?php

// new file formats support
$msgValue       = '';
$msgTranslation = 'Please choose a file format...';
$values = array();
$values[ 0 ] = $msgValue;
$values = array_merge( $values, $fileFormat->possibleValues());

sort($values);

foreach($values as $key => $value) {
  $selected = "";

  if ( $value == $msgValue ) {
      $translation = $msgTranslation;
  } else {
      $translation = $fileFormat->translatedValueFor( $value );
    if (stristr($value, "tiff")) {
      $translation .= " (*.tiff)";
    }
    
    if ($value == $fileFormat->value()) {
      $selected = " selected=\"selected\"";      
    }

    $extensions = $fileFormat->fileExtensions($value);
    $extension = $extensions[0];
  }
?>
      <option <?php echo "name = \"" . $value . "\"  value = \"" . $extension  . "\"" . $selected ?>>
           <?php echo $translation ?>
           </option>
<?php

}

?>

</select>
</fieldset>
        
            <fieldset>
                <legend>Images available on server</legend>
                <div id="userfiles" onmouseover="showPreview()">
<?php

$flag = "";
if ($files == null) {
    $flag = " disabled=\"disabled\"";
    $message .= "";
}

?>

                    <select onchange="javascript:imageAction(this)"
                            id = "filesPerExtension"
                            name="userfiles[]"
                            size="10"
                            multiple="multiple"<?php echo $flag ?>>
<?php
$keyArr = array();
if ($files == null) {
    echo "                        <option>&nbsp;</option>\n";
} else {
    if ($fileFormat->value() != "") {
        $format = $fileFormat->value();
        $extensions = $fileFormat->fileExtensions($format);
        $extension  = $extensions[0];
        
        foreach ($files as $key => $file) {
            if ($_SESSION['fileserver']->getExtension($file) == $extension) {
                echo "<option>" . $file . "</option>\n";       
            }
        }
    }
}


?>
                    </select>
                </div>
            </fieldset>
            
            <div id="selection">

              <input name="down"
                type="submit"
                value="" 
                class="icon down"
                onmouseover="TagToTip('ttSpanDown')"
                onmouseout="UnTip()" />
    
              <input name="up"
                type="submit"
                value=""
                class="icon remove"
                onmouseover="TagToTip('ttSpanUp')"
                onmouseout="UnTip()" />

    <label>

              <input type="checkbox"
                name="series"
                class="series"
                id="series"
                value="autoseries"
                onclick="javascript:storeFileFormatSelection(ImageFileFormat,this)" />
    Automatically load file series
    </label>
            </div>
            
            <fieldset>
                <legend>Selected images</legend>
                <div id="selectedfiles" onmouseover="showPreview()">
<?php

$files = $_SESSION['fileserver']->selectedFiles();
$flag = "";
if ($files == null) {
    $flag = " disabled=\"disabled\"";
}

?>
                    <select onclick="javascript:imageAction(this)" 
                            onchange="javascript:imageAction(this)"
                            id = "selectedimages"
                            name="selectedfiles[]"
                            size="5"
                            multiple="multiple"<?php echo $flag ?>>
<?php                     
if ($files != null) {
  foreach ($files as $filename) {
          $key = $keyArr[$filename];
          echo $_SESSION['fileserver']->getImageOptionLine($filename,
              $key, "src", "preview", 0, 1) ;
  }
}
else echo "                        <option>&nbsp;</option>\n";

?>
                    </select>
                </div>
            </fieldset>
            
            <div id="actions" class="imageselection"
                 onmouseover="showInstructions()">
                <input name="update"
                       type="submit"
                       value=""
                       class="icon update"
                       onmouseover="TagToTip('ttSpanRefresh')"
                       onmouseout="UnTip()"
                       />
                <input name="OK" type="hidden" />
            </div>
            
            <div id="controls"
                 onmouseover="showInstructions()">
              <input type="submit"
                     value=""
                     class="icon next"
                     onclick="process()"
                     onmouseover="TagToTip('ttSpanForward')"
                     onmouseout="UnTip()" />
            </div>

        </form>

    </div> <!-- content -->

    <script type="text/javascript">
        <!--
            window.pageInstructions='<?php echo escapeJavaScript($info); ?>';
            window.infoShown = true;
            window.previewSelected = -1;
        -->
    </script>


    <div id="rightpanel">

        <div id="info">
        <?php echo $info; ?>
        </div>
        
        <div id="message">
<?php

echo "<p>$message</p>";

?>
        </div>
        
    </div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>
