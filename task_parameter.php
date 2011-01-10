<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once("./inc/User.inc");
require_once("./inc/Parameter.inc");
require_once("./inc/Setting.inc");
require_once("./inc/Util.inc");
require_once ("./inc/System.inc");

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

// Check if the SNR estimator can be turned on
$estimateSNR = false;
$version = System::huCoreVersion();
if ( $useThumbnails && $genThumbnails && $version >= 3050100 ) {
  $estimateSNR = true;
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
  header("Location: " . "login.php"); exit();
}

if (!isset($_SESSION['task_setting'])) {
  $_SESSION['task_setting'] = new TaskSetting();
}
if ($_SESSION['user']->isAdmin()) {
  $_SESSION['task_setting']->setNumberOfChannels(5);
}
else {
  $_SESSION['task_setting']->setNumberOfChannels($_SESSION['setting']->numberOfChannels());
}

$message = "            <p class=\"warning\">&nbsp;<br />&nbsp;</p>\n";

/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ( $_SESSION[ 'task_setting' ]->checkPostedTaskParameters( $_POST ) ) {
  $saved = $_SESSION['task_setting']->save();
  if ($saved) {
    header("Location: " . "select_task_settings.php"); exit();
  } else {
    $message = "            <p class=\"warning\">" .
      $_SESSION['task_setting']->message() . "</p>\n";  
  }
} else {
  $message = "            <p class=\"warning\">" .
    $_SESSION['task_setting']->message() . "</p>\n";  
}

/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

//$noRange = False;

// Javascript includes
$script = array( "settings.js", "quickhelp/help.js",
                "quickhelp/taskParameterHelp.js" );

include("header.inc.php");

?>
    <!--
      Tooltips
    -->
    <span id="ttSpanCancel">Abort editing and go back to the Restoration parameters selection page. All changes will be lost!</span>  
    <span id="ttSpanForward">Save your settings.</span>
    <?php if ($estimateSNR) { ?>
    <span id="ttEstimateSnr">Use a sample raw image to find a SNR estimate for each channel.</span>
    <?php } ?>
    
    <div id="nav">
        <ul>
            <li><?php echo $_SESSION['user']->name(); ?></li>
            <li><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=HuygensRemoteManagerHelpRestorationParameters')"><img src="images/help.png" alt="help" />&nbsp;Help</a></li>
        </ul>
    </div>
    
    <div id="content">
    
        <h3>Task Setting</h3>
        
        <form method="post" action="" id="select">
          
           <h4>How should your images be restored?</h4>
           
             <fieldset class="setting" 
              onmouseover="javascript:changeQuickHelp( 'method' );" >  <!-- deconvolution algorithm -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/RestorationMethod')"><img src="images/help.png" alt="?" /></a>
                    deconvolution algorithm
                </legend>

                <select name="DeconvolutionAlgorithm"
                  onChange="javascript:switchSnrMode();" >
                
<?php

/*
                           DECONVOLUTION ALGORITHM
*/

$parameter = $_SESSION['task_setting']->parameter("DeconvolutionAlgorithm");
$possibleValues = $parameter->possibleValues();
$selectedMode  = $parameter->value();
foreach($possibleValues as $possibleValue) {
  $translation = $parameter->translatedValueFor( $possibleValue );
  if ( $possibleValue == $selectedMode ) {
      $option = "selected=\"selected\"";
  } else {
      $option = "";
  }
?>
                    <option <?php echo $option?> value="<?php echo $possibleValue?>"><?php echo $translation?></option>
<?php
}
?>
                </select>
                
            </fieldset>
        
            <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'snr' );" >  <!-- signal/noise ratio -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=SignalToNoiseRatio')"><img src="images/help.png" alt="?" /></a>
                    signal/noise ratio
                </legend>

                <div id="snr" onmouseover="javascript:changeQuickHelp( 'snr' );">
                      
<?php

$visibility = " style=\"display: none\"";
if ($selectedMode == "cmle") {
  $visibility = " style=\"display: block\"";
}

?>
                    <div id="cmle-snr"
                         class="multichannel"<?php echo $visibility?>>
                    <ul>
                      <li>SNR: 
                      <div class="multichannel">
<?php

/*
                           SIGNAL-TO-NOISE RATIO
*/

  $signalNoiseRatioParam = $_SESSION['task_setting']->parameter("SignalNoiseRatio");
  $signalNoiseRatioValue = $signalNoiseRatioParam->value();

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
  
    $value = "";
    if ($selectedMode == "cmle")
        $value = $signalNoiseRatioValue[$i];


?>
                          <span class="nowrap">Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;<span class="multichannel"><input name="SignalNoiseRatioCMLE<?php echo $i ?>" type="text" size="8" value="<?php echo $value ?>" class="multichannelinput" /></span>&nbsp;</span>
<?php

}

?>
                          </div>
                        </li>
                      </ul>

                    <?php
                    if ($estimateSNR) {
                        echo "<a href=\"estimate_snr_from_image.php\"
                          onmouseover=\"TagToTip('ttEstimateSnr' )\"
                          onmouseout=\"UnTip()\"
                        ><img src=\"images/calc_small.png\" alt=\"\" />";
                        echo " Estimate SNR from image</a>";
                    }

                    ?>
                    </div>
<?php

$visibility = " style=\"display: none\"";
if ($selectedMode == "qmle") {
  $visibility = " style=\"display: block\"";
}

?>
                    <div id="qmle-snr" 
                      class="multichannel"<?php echo $visibility?>>
                      <ul>
                        <li>SNR:
                        <div class="multichannel">
<?php

for ($i = 0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {

?>
                        <span class="nowrap">Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;
                            <select class="snrselect" name="SignalNoiseRatioQMLE<?php echo $i ?>">
<?php

  for ($j = 1; $j <= 4; $j++) {
      $option = "                                <option ";
      if (isset($signalNoiseRatioValue)) {
          if ($signalNoiseRatioValue[$i] >= 1 && $signalNoiseRatioValue[$i] <= 4) {
            if ($j == $signalNoiseRatioValue[$i])
                $option .= "selected=\"selected\" ";
          }
          else {
              if ($j == 2)
                $option .= "selected=\"selected\" ";
          }
      }
      else {
          if ($j == 2)
            $option .= "selected=\"selected\" ";
      }
      $option .= "value=\"".$j."\">";
      if ($j == 1)
        $option .= "low</option>";
      else if ($j == 2)
        $option .= "fair</option>";
      else if ($j == 3)
        $option .= "good</option>";
      else if ($j == 4)
        $option .= "inf</option>";
      echo $option;
  }

?>
                            </select>
                        </span><br />
<?php

}

?>
                          </div>
                        </li>
                      </ul>
                    </div>
                    
                </div>
                
            </fieldset>
            
            <fieldset class="setting"
              onmouseover="javascript:changeQuickHelp( 'background' );" >  <!-- background mode -->
            
                <legend>
                    <a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=BackgroundMode')"><img src="images/help.png" alt="?" /></a>
                    background mode
                </legend>
                
                <div id="background">
                
<?php

/*
                           BACKGROUND OFFSET
*/

$backgroundOffsetPercentParam =  $_SESSION['task_setting']->parameter("BackgroundOffsetPercent");
$backgroundOffset = $backgroundOffsetPercentParam->internalValue();

$flag = "";
if ($backgroundOffset[0] == "" || $backgroundOffset[0] == "auto") $flag = " checked=\"checked\"";

?>

                    <p><input type="radio" name="BackgroundEstimationMode" value="auto"<?php echo $flag ?> />automatic background estimation</p>
                    
<?php

$flag = "";
if ($backgroundOffset[0] == "object") $flag = " checked=\"checked\"";

?>

                    <p><input type="radio" name="BackgroundEstimationMode" value="object"<?php echo $flag ?> />in/near object</p>
                    
<?php

$flag = "";
if ($backgroundOffset[0] != "" && $backgroundOffset[0] != "auto" && $backgroundOffset[0] != "object") $flag = " checked=\"checked\"";

?>
                    <input type="radio" name="BackgroundEstimationMode" value="manual"<?php echo $flag ?> />
                    remove constant absolute value:
                    
                    <div class="multichannel">
<?php

for ($i=0; $i < $_SESSION['task_setting']->numberOfChannels(); $i++) {
  $val = "";
  if ($backgroundOffset[0] != "auto" && $backgroundOffset[0] != "object") $val = $backgroundOffset[$i];

?>
                        <span class="nowrap">Ch<?php echo $i ?>:&nbsp;&nbsp;&nbsp;<span class="multichannel"><input name="BackgroundOffsetPercent<?php echo $i ?>" type="text" size="8" value="<?php echo $val ?>" class="multichannelinput" /></span>&nbsp;</span>
                        
<?php

}

/*!
	\todo	The visibility toggle should be restored but but only the quality change
			should be hidden for qmle, not the whole stopping criteria div!
			Also restore the changeVisibility("cmle-it") call in scripts/settings.js.
 */
//$visibility = " style=\"display: none\"";
//if ($selectedMode == "cmle") {
  $visibility = " style=\"display: block\"";
//}

?>
                    </div>
                    
                </div>
                
            </fieldset>

            <div id="cmle-it" <?php echo $visibility ?>>

            <fieldset class="setting" 
              onmouseover="javascript:changeQuickHelp( 'stopcrit' );" >  <!-- stopping criteria -->
            
                <legend>
                    stopping criteria
                </legend>
                
                <div id="criteria">
                <p>
                
                    <p><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=MaxNumOfIterations')"><img src="images/help.png" alt="?" /></a>
                    number of iterations:
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("NumberOfIterations");
//$value = 40;
//if ($parameter->value() != NULL) {
$value = $parameter->value();
//}

?>
                    <input name="NumberOfIterations" type="text" size="8" value="<?php echo $value ?>" />
                    
                    </p><p>
                    
                    <p><a href="javascript:openWindow('http://support.svi.nl/wiki/style=hrm&amp;help=QualityCriterion')"><img src="images/help.png" alt="?" /></a>
                    quality change:
                    
<?php

$parameter = $_SESSION['task_setting']->parameter("QualityChangeStoppingCriterion");
//$value = 0.1;
//if ($parameter->value() != null) {
$value = $parameter->value();
//}

?>
                    <input name="QualityChangeStoppingCriterion" type="text" size="3" value="<?php echo $value ?>" />
                    </p>
                    
                    </p>
                    
                </div>
                
            </fieldset>
            </div>
            
            <div><input name="OK" type="hidden" /></div>
            
            <div id="controls" onmouseover="javascript:changeQuickHelp( 'default' )">
              <input type="button" value="" class="icon up"
                  onmouseover="TagToTip('ttSpanCancel' )"
                  onmouseout="UnTip()"
                  onclick="document.location.href='select_task_settings.php'" />
              <input type="submit" value="" class="icon save"
                  onmouseover="TagToTip('ttSpanForward' )"
                  onmouseout="UnTip()"
                  onclick="process()" />
            </div>

        </form>
        
    </div> <!-- content -->

    <div id="rightpanel" onmouseover="javascript:changeQuickHelp( 'default' )">
    
      <div id="info">
      <h3>Quick help</h3>
        <div id="contextHelp">
          <p>On this page you specify the parameters for restoration.</p>
          <p>These parameters comprise the deconvolution algorithm, the
          signal-to-noise ratio (SNR) of the images, the mode for background
          estimation, and the stopping criteria.</p>
        </div>
     </div>
        
      <div id="message">
<?php

echo $message;

?>
        </div>
        
    </div> <!-- rightpanel -->
    
<?php

include("footer.inc.php");

?>