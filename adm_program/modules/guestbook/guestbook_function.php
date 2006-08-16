<?php
/******************************************************************************
 * Verschiedene Funktionen fuer das Gaestebuch
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * id:    ID die bearbeitet werden soll
 * mode:     1 - Neue Gaestebucheintrag anlegen
 *           2 - Gaestebucheintrag loeschen
 *           3 - Gaestebucheintrag editieren
 *           4 - Kommentar zu einem Eintrag anlegen
 *           5 - Kommentar eines Gaestebucheintrages loeschen
 * url:      kann beim Loeschen mit uebergeben werden
 * headline: Ueberschrift, die ueber den Gaestebuch steht
 *           (Default) Gaestebuch
 *
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

// Uebergabevariablen pruefen

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}


if (array_key_exists("mode", $_GET))
{
    if (is_numeric($_GET["mode"]) == false)
    {
        $g_message->show("invalid");
    }
}


if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "G&auml;stebuch";
}


// Erst einmal pruefen ob die noetigen Berechtigungen vorhanden sind
if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 4 || $_GET['mode'] == 5)
{
    // Der User muss fuer diese modes eingeloggt sein
    require("../../system/login_valid.php");

    if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 5)
    {
        // Fuer die modes 2,3 und 5 werden editGuestbook-Rechte benoetigt
        if(!editGuestbook())
        {
            $g_message->show("norights");
        }
    }

    if ($_GET['mode'] == 4)
    {
        // Fuer den mode 4 werden commentGuestbook-Rechte benoetigt
        if(!commentGuestbook())
        {
            $g_message->show("norights");
        }
    }


    // Abschliessend wird jetzt noch geprueft ob die uebergebene ID ueberhaupt zur Orga gehoert
    if ($_GET['mode'] == 2 || $_GET['mode'] == 3 || $_GET['mode'] == 4)
    {
        $sql    = "SELECT * FROM ". TBL_GUESTBOOK. " WHERE gbo_id = {0} and gbo_org_id = $g_current_organization->id";
        $sql    = prepareSQL($sql, array($_GET['id']));
    }

    if ($_GET['mode'] == 5)
    {
        $sql    = "SELECT * FROM ". TBL_GUESTBOOK_COMMENTS. ", ". TBL_GUESTBOOK. " WHERE gbc_id = {0} and gbo_org_id = $g_current_organization->id";
        $sql    = prepareSQL($sql, array($_GET['id']));
    }

    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (mysql_num_rows($result) == 0)
    {
        $g_message->show("invalid");
    }


}


$err_code = "";
$err_text = "";


if ($_GET["mode"] == 1 || $_GET["mode"] == 3)
{
    // Der Inhalt des Formulars wird nun in der Session gespeichert...
    $_SESSION['guestbook_entry_request'] = $_REQUEST;


    // Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
    // muss natuerlich der Code ueberprueft werden
    if ($_GET["mode"] == 1 && !$g_session_valid && strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']))
    {
        $g_message->show("captcha_code");
    }


    // Daten fuer die DB werden nun aufbereitet...

    $name      = strStripTags($_POST['name']);
    $text      = strStripTags($_POST['text']);

    $email     = strStripTags($_POST['email']);
    if (!isValidEmailAddress($email))
    {
        // falls die Email ein ungueltiges Format aufweist wird sie einfach auf null gesetzt
        $email = null;
    }


    $homepage  = strStripTags($_POST['homepage']);
    if (strlen($homepage) != 0)
    {
        // Die Webadresse wird jetzt, falls sie nicht mit http:// oder https:// beginnt, entsprechend aufbereitet
        if (substr($homepage, 0, 7) != 'http://' && substr($homepage, 0, 8) != 'https://' )
        {
            $homepage = "http://". $homepage;
        }
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $actDate   = date("Y.m.d G:i:s", time());


    if (strlen($name) > 0 && strlen($text)  > 0)
    {

        // Gaestebucheintrag speichern

        if ($_GET['id'] == 0)
        {
            if ($g_session_valid)
            {
                // Falls der User eingeloggt ist wird die aktuelle UserId mitabgespeichert...
                $sql = "INSERT INTO ". TBL_GUESTBOOK. " (gbo_org_id, gbo_usr_id, gbo_name, gbo_text, gbo_email,
                                                         gbo_homepage, gbo_timestamp, gbo_ip_address)
                                         VALUES ($g_current_organization->id, $g_current_user->id, {0}, {1}, {2},
                                                 {3}, '$actDate', '$ipAddress')";
            }
            else
            {
                // Falls er nicht engeloggt ist, gibt es das sql-Statement natürlich ohnr die UserID
                $sql = "INSERT INTO ". TBL_GUESTBOOK. " (gbo_org_id, gbo_name, gbo_text, gbo_email,
                                                         gbo_homepage, gbo_timestamp, gbo_ip_address)
                                         VALUES ($g_current_organization->id, {0}, {1}, {2},
                                                 {3}, '$actDate', '$ipAddress')";
            }

            $sql    = prepareSQL($sql, array($name, $text, $email, $homepage));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);

        }
        else
        {
            $sql = "UPDATE ". TBL_GUESTBOOK. " SET  gbo_name     = {0}
                                                  , gbo_text     = {1}
                                                  , gbo_email    = {2}
                                                  , gbo_homepage = {3}
                                                  , gbo_last_change    = '$actDate'
                                                  , gbo_usr_id_change = $g_current_user->id
                     WHERE gbo_id = {4}";
            $sql    = prepareSQL($sql, array($name, $text, $email, $homepage, $_GET['id']));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }

        // Der Inhalt des Formulars wird bei erfolgreichem insert/update aus der Session geloescht
        unset($_SESSION['guestbook_entry_request']);

        // Der CaptchaCode wird bei erfolgreichem insert/update aus der Session geloescht
        if (isset($_SESSION['captchacode']))
        {
            unset($_SESSION['captchacode']);
        }


        $location = "Location: $g_root_path/adm_program/modules/guestbook/guestbook.php?headline=". $_GET['headline'];
        header($location);
        exit();
    }
    else
    {
        if(strlen($name) > 0)
        {
            $err_text = "Text";
        }
        else
        {
            $err_text = "Name";
        }
        $err_code = "feld";
    }
}

elseif($_GET["mode"] == 2)
{
    //erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
    $sql = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_gbo_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    //dann den Eintrag selber loeschen...
    $sql = "DELETE FROM ". TBL_GUESTBOOK. " WHERE gbo_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}

elseif($_GET["mode"] == 4)
{
    //Daten fuer die DB vorbereiten
    $text      = strStripTags($_POST['text']);
    $actDate   = date("Y.m.d G:i:s", time());

    if (strlen($text)  > 0)
    {
        $sql = "INSERT INTO ". TBL_GUESTBOOK_COMMENTS. " (gbc_gbo_id, gbc_usr_id, gbc_text, gbc_timestamp)
                                                 VALUES ({0}, $g_current_user->id, {1}, '$actDate')";
        $sql    = prepareSQL($sql, array($_GET['id'], $text));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        $location = "Location: $g_root_path/adm_program/modules/guestbook/guestbook.php?id=". $_GET['id']. "&headline=". $_GET['headline'];
        header($location);
        exit();

    }
    else
    {
        $err_text = "Text";
        $err_code = "feld";
    }


}

elseif ($_GET["mode"] == 5)
{
    //Gaestebuchkommentar loeschen...
    $sql = "DELETE FROM ". TBL_GUESTBOOK_COMMENTS. " WHERE gbc_id = {0}";
    $sql    = prepareSQL($sql, array($_GET['id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    if (!isset($_GET["url"]))
    {
        $_GET["url"] = "$g_root_path/$g_main_page";
    }

    $g_message->setForwardUrl($_GET["url"]);
    $g_message->show("delete");
}

else
{
    // Falls der Mode unbekannt ist, ist natürlich auch Ende...
    $g_message->show("invalid");
}

$g_message->show($err_code, $err_text);
?>