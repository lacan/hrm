<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

use hrm\Nav;
use hrm\param\ChromaticAberration;
use hrm\Util;

require_once dirname(__FILE__) . '/inc/bootstrap.php';

/* *****************************************************************************
 *
 * START SESSION, CHECK LOGIN STATE, INITIALIZE WHAT NEEDED
 *
 **************************************************************************** */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}
$message = "";

/* *****************************************************************************
 *
 * MANAGE THE CHROMATIC ABERRATION
 *
 **************************************************************************** */

/** @var ChromaticAberration $parameter */
$chromaticParam = $_SESSION['task_setting']->parameter("ChromaticAberration");
$chanCnt = $_SESSION['task_setting']->numberOfChannels();
$componentCnt = $chromaticParam->componentCnt();
$chromaticArray = $chromaticParam->value();
ksort($chromaticArray);


/* *****************************************************************************
 *
 * PROCESS THE POSTED PARAMETERS
 *
 **************************************************************************** */

if ($_SESSION['task_setting']->checkPostedChromaticAberrationParameters($_POST)) {
    $saved = $_SESSION['task_setting']->save();
    if ($saved) {
        header("Location: " . "select_task_settings.php");
        exit();
    } else {
        $message = $_SESSION['task_setting']->message();
    }
} else {
    $message = $_SESSION['task_setting']->message();
}


/* *****************************************************************************
 *
 * CREATE THE PAGE
 *
 **************************************************************************** */

// Javascript includes
$script = array("settings.js", "quickhelp/help.js",
    "quickhelp/taskParameterHelp.js");

include("header.inc.php");
?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">
        Abort editing and go back to the Restoration parameters
        selection page. All changes will be lost!
    </span>
<span class="toolTip" id="ttSpanSave">
        Save and return to the processing parameters selection page.
    </span>
<span class="toolTip" id="ttSpanBack">
        Go back to previous page.
    </span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
            echo(Nav::linkWikiPage('HuygensRemoteManagerHelpRestorationParameters'));
            ?>
            <li> [ <?php echo $_SESSION['task_setting']->name(); ?> ]</li>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
            echo(Nav::textUser($_SESSION['user']->name()));
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>


<div id="content">
    <h2>Restoration - Post Deconvolution</h2>
    <div id="cac">


        <fieldset class="setting provided"
                  onmouseover="changeQuickHelp( 'chromatic' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/ChromaticAberrationCorrector')">
                    <img src="images/help.png" alt="?"/>
                </a>
                correct images for chromatic aberration?
            </legend>

            <p>Multi-channel images often display chromatic aberrations.
                Correcting for this is crucial for image visualization and
                analysis.</p>


            Reference channel:

            <select name="ReferenceChannel"
                    id="ReferenceChannel"
                    title="Reference channel"
                    onclick="changeChromaticChannelReference(this)"
                    onchange="changeChromaticChannelReference(this)">
                <?php
                for ($chan = 0; $chan < $chanCnt; $chan++) {
                    ?>
                    <option value=<?php echo $chan; ?>>
                        <?php echo $chan; ?>
                    </option>
                    <?php
                }
                ?>
            </select>


            <form method="post" action="" id="select">
                <table id="ChromaticAberration">
                    <tr>
                        <td class="header">Ch</td>
                        <td class="header">Shift x<br/>(&#956m)</td>
                        <td class="header">Shift y<br/>(&#956m)</td>
                        <td class="header">Shift z<br/>(&#956m)</td>
                        <td class="header">Rotation<br/>(degrees)</td>
                        <td class="header">Scale<br/>(ratio)</td>
                    </tr>

                    <?php
                    for ($chan = 0; $chan < $chanCnt; $chan++) {
                        $offset = $chan * $componentCnt;
                        ?>
                        <tr>
                            <td class="header"><?php echo $chan; ?></td>

                            <?php

                            for ($component = 0; $component < $componentCnt; $component++) {
                                ?>

                                <td><input
                                        id="ChromaticAberrationCh<?php echo $chan . '_' . $component; ?>"
                                        name="ChromaticAberrationCh<?php echo $chan . '_' . $component; ?>"
                                        title="Channel <?php echo $chan; ?>, component <?php echo $component; ?>"
                                        type="text"
                                        size="1"
                                        value="<?php echo $chromaticArray[$offset]; ?>"
                                        class="multichannelinput"/></td>
                                <?php
                                $offset++;
                            }
                            ?>
                        </tr>
                        <?php
                    }
                    ?>

                </table>
                <p class="info">The correction is optional: leave empty for
                    skipping.</p>

    </div> <!-- ChromaticAberrationCorrector -->


    <div id="TStabilization">

        <fieldset class="setting provided"
                  onmouseover="changeQuickHelp( 'tstabilization' );">

            <legend>
                <a href="javascript:openWindow(
                       'http://www.svi.nl/ObjectStabilizer')">
                    <img src="images/help.png" alt="?"/>
                </a>
                stabilize time series?
            </legend>

            <p>Correct for cell motion, thermal drift, shaking, and other
               types of movement such as x-y-z translations and rotations.</p>


            Stabilization of Time Series:
            <select name="TStabilization"
                    id="TStabilization"
                    title="Stabilization of Time Series"
                    onclick="changeTStabilization(this)"
                    onchange="changeTStabilization(this)">
            </select>

            <br /><br />
            
            Stabilization Method:
            <select name="TStabilizationMethod"
                    id="TStabilizationMethod"
                    title="Stabililzation Method">
            </select>

            <br /><br />
            
            Detect Rotations:
            <select name="TStabilizationRotation"
                    id="TStabilizationRotation"
                    title="Stabililzation Rotation">
            </select>

            <br /><br />

            Cropping Scheme:
            <select name="TStabilizationCropping"
                    id="TStabilizationCropping"
                    title="Stabililzation Cropping">
            </select>


    </div> <!-- TStabilization -->




    <div><input name="OK" type="hidden"/></div>

    <div id="controls"
         onmouseover="changeQuickHelp( 'default' )">
        <input type="button" value="" class="icon previous"
               onmouseover="TagToTip('ttSpanBack' )"
               onmouseout="UnTip()"
               onclick="document.location.href='task_parameter.php'"/>
        <input type="button" value="" class="icon up"
               onmouseover="TagToTip('ttSpanCancel' )"
               onmouseout="UnTip()"
               onclick="deleteValuesAndRedirect(
                    'select_task_settings.php' );"/>
        <input type="submit" value="" class="icon save"
               onmouseover="TagToTip('ttSpanSave' )"
               onmouseout="UnTip()"
               onclick="process()"/>
    </div>

    </form>
</div> <!-- content -->

<div id="rightpanel" onmouseover="changeQuickHelp( 'default' )">
    <div id="info">
        <h3>Quick help</h3>
        <div id="contextHelp">
            <p>On this page you specify the parameters for the chromatic
                aberration correction.</p>
            <p>These parameters comprise the shifts along x, y, z, the rotations
                and the zoom factors across channels.</p>
        </div>
    </div>

    <div id="message">
        <?php

        echo "<p>$message</p>";

        ?>
    </div>

</div> <!-- rightpanel -->


<script type="text/javascript">
    initChromaticChannelReference();
</script>

<?php
include("footer.inc.php");

// Workaround for IE
if (Util::using_IE() && !isset($_SERVER['HTTP_REFERER'])) {
    ?>
    <script type="text/javascript">
        $(document).ready(retrieveValues());
    </script>
    <?php
}
?>
