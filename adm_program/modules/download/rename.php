﻿<?php
/******************************************************************************
 * Verwaltung der Downloads
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Martin Günzler
 *
 * Uebergaben:
 *
 * folder :  relativer Pfad zu der Datei / Ordners
 * default_folder : gibt den Ordner in adm_my_files/download an, ab dem die
 *                  Verzeichnisstruktur angezeigt wird. Wurde ein Default-Ordner
 *                  gesetzt, kann der Anwender nur noch in Unterordner und nicht
 *                  in hoehere Ordner des Default-Ordners navigieren
 * file   :  die Datei / der Ordner der / die verarbeitet wird
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// erst prüfen, ob der User auch die entsprechenden Rechte hat
if(!editDownload())
{
    $g_message->show("norights");
}


$default_folder = strStripTags(urldecode($_GET['default_folder']));
$folder     = strStripTags(urldecode($_GET['folder']));
$file       = strStripTags($_GET['file']);
$act_folder = "../../../adm_my_files/download";
$datei = "";

// uebergebene Ordner auf Gueltigkeit pruefen
// und Ordnerpfad zusammensetzen
if(strlen($default_folder) > 0)
{
    if(strpos($default_folder, "..") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $act_folder = "$act_folder/$default_folder";
}

if(strlen($folder) > 0)
{
    if(strpos($folder, "..") !== false)
    {
        $g_message->show("invalid_folder");
    }
    $act_folder = "$act_folder/$folder";
}

if(strpos($file, "..") !== false
|| strlen($file) == 0)
{
    $g_message->show("invalid_folder");
}

//Beginn der Seite
echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Umbenennen</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";
    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    //Beginn des Inhaltes
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
        $datei = explode(".","$file");
        echo "<p>&nbsp;</p>
        <form method=\"POST\" action=\"download_function.php?mode=4&amp;folder=". urlencode($folder). "&amp;default_folder=". urlencode($default_folder). "&amp;file=". urlencode($file). "\">
            <div class=\"formHead\" style=\"width: 400px\">Datei/Ordner umbenennen</div>
            <div class=\"formBody\" style=\"width: 400px\">
                <div>
                    <div style=\"text-align: right; width: 35%; float: left;\">Bisheriger Name:</div>
                    <div style=\"text-align: left; margin-left: 37%;\">$datei[0]</div>
                </div>
                <div style=\"margin-top: 10px;\">
                    <div style=\"text-align: right; width: 35%; float: left;\">Neuer Name:</div>
                    <div style=\"text-align: left; margin-left: 37%;\">
                        <input type=\"text\" id=\"new_name\" name=\"new_name\" size=\"25\" tabindex=\"1\">";
                        if(strlen($datei[1]) > 0)
                        {
                            echo ".$datei[1]";
                        }
                        echo "&nbsp;<img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=dateiname','Message','width=400,height=250,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>

                <hr style=\"margin-top: 10px; margin-bottom: 10px;\" width=\"85%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"umbenennen\" type=\"submit\" value=\"umbenennen\" tabindex=\"2\">
                    <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hochladen\">
                    &nbsp;Umbenennen</button>
                </div>
            </div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--
        document.getElementById('new_name').focus();
    --></script>";   
    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>