<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Uebergaben:
 *
 * usr_id  - E-Mail an den entsprechenden Benutzer schreiben
 * rol_id  - E-Mail an alle Mitglieder der Rolle schreiben
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
require("../../system/email_class.php");

// Uebergabevariablen pruefen

if (isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)
{
    $g_message->show("invalid");
}

if (strlen($_POST["rol_id"]) > 0 && is_numeric($_POST["rol_id"]) == false)
{
    $g_message->show("invalid");
}


// Pruefungen, ob die Seite regulaer aufgerufen wurde

// in ausgeloggtem Zustand duerfen nie direkt usr_ids uebergeben werden...
if (array_key_exists("usr_id", $_GET) && !$g_session_valid)
{
    $g_message->show("invalid");
}

// aktuelle Seite im NaviObjekt speichern. Dann kann in der Vorgaengerseite geprueft werden, ob das
// Formular mit den in der Session gespeicherten Werten ausgefuellt werden soll...
$_SESSION['navigation']->addUrl($g_current_url);

// Falls eine Usr_id uebergeben wurde, muss geprueft werden ob der User ueberhaupt
// auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...
if (array_key_exists("usr_id", $_GET))
{
    if (!editUser())
    {
        $sql    = "SELECT DISTINCT usr_id, usr_email
                     FROM ". TBL_USERS. ", ". TBL_MEMBERS. ", ". TBL_ROLES. "
                    WHERE mem_usr_id = usr_id
                      AND mem_rol_id = rol_id
                      AND rol_org_shortname = '$g_current_organization->shortname'
                      AND usr_id  = {0} ";
    }
    else
    {
        $sql    = "SELECT usr_id, usr_email
                     FROM ". TBL_USERS. "
                    WHERE usr_id  = {0} ";
    }
    $sql    = prepareSQL($sql, array($_GET['usr_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_object($result);

    if (mysql_num_rows($result) != 1)
    {
        $g_message->show("usrid_not_found");
    }

    if (!isValidEmailAddress($row->usr_email))
    {
        $g_message->show("usrmail_not_found");
    }
}

// Falls Attachmentgroesse die max_post_size aus der php.ini uebertrifft, ist $_POST komplett leer.
// Deswegen muss dies ueberprueft werden...
if (empty($_POST))
{
    $g_message->show("invalid");
}

if ($g_preferences['send_email_extern'] == 1)
{
    // es duerfen oder koennen keine Mails ueber den Server verschickt werden
    $g_message->show("mail_extern");
}

// Falls der User nicht eingeloggt ist, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if (!$g_session_valid && $g_preferences['enable_mail_captcha'] == 1)
{
    if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
    {
        $g_message->show("captcha_code");
    }
}


$err_code = "";
$err_text = "";


$_POST['mailfrom'] = trim($_POST['mailfrom']);
$_POST['name']     = trim($_POST['name']);
$_POST['subject']  = trim($_POST['subject']);

//Erst mal ein neues Emailobjekt erstellen...
$email = new Email();


//Nun der Mail die Absenderangaben,den Betreff und das Attachment hinzufuegen...
if (strlen($_POST['name']) > 0)
{
    //Absenderangaben checken falls der User eingeloggt ist, damit ein paar schlaue User nicht einfach die Felder aendern koennen...
    if ( $g_session_valid && ($_POST['mailfrom'] != $g_current_user->email || $_POST['name'] != "$g_current_user->first_name $g_current_user->last_name") )
    {
        $g_message->show("invalid");
    }


    //Absenderangaben setzen
    if ($email->setSender($_POST['mailfrom'],$_POST['name']))
    {
      //Betreff setzen
      if ($email->setSubject($_POST['subject']))
      {
            //Pruefen ob moeglicher Weise ein Attachment vorliegt
            if (isset($_FILES['userfile']))
            {
                //noch mal schnell pruefen ob der User wirklich eingelogt ist...
                if (!$g_session_valid)
                {
                    $g_message->show("invalid");
                }

                //Pruefen ob ein Fehler beim Upload vorliegt
                if (($_FILES['userfile']['error'] != 0) &&  ($_FILES['userfile']['error'] != 4))
                {
                    $err_code = "attachment";
                }
                //Wenn ein Attachment vorliegt dieses der Mail hinzufuegen
                if ($_FILES['userfile']['error'] == 0)
                {
                    if (strlen($_FILES['userfile']['type']) > 0)
                    {
                        $email->addAttachment($_FILES['userfile']['tmp_name'], $_FILES['userfile']['name'], $_FILES['userfile']['type']);
                    }
                    // Falls kein ContentType vom Browser uebertragen wird,
                    // setzt die MailKlasse automatisch "application/octet-stream" als FileTyp
                    else
                    {
                        $email->addAttachment($_FILES['userfile']['tmp_name'], $_FILES['userfile']['name']);
                    }
                }
            }
        }
        else
        {
            $err_code = "feld";
            $err_text = "Betreff";
        }
    }
    else
    {
        $err_code = "email_invalid";
    }
}
else
{
    $err_code = "feld";
    $err_text = "Name";
}

if (array_key_exists("rol_id", $_POST) && strlen($err_code) == 0)
{

    if (strlen($_POST['rol_id']) == 0)
    {
        $err_code = "mail_rolle";
    }
    else
    {
        if ($g_session_valid)
        {
            $sql    = "SELECT rol_mail_login FROM ". TBL_ROLES. "
                       WHERE rol_org_shortname    = '$g_organization'
                       AND rol_id = {0} ";
        }
        else
        {
            $sql    = "SELECT rol_mail_logout FROM ". TBL_ROLES. "
                       WHERE rol_org_shortname    = '$g_organization'
                       AND rol_id = {0} ";
        }
        $sql    = prepareSQL($sql, array($_POST['rol_id']));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $row = mysql_fetch_array($result);

        if ($row[0] != 1)
        {
            $err_code = "invalid";
        }
    }
}


//Pruefen ob bis hier Fehler aufgetreten sind
if (strlen($err_code) > 0)
{
    $g_message->show($err_code, $err_text);
}

//Nun die Empfaenger zusammensuchen und an das Mailobjekt uebergeben
if (array_key_exists("usr_id", $_GET))
{
    //usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
    $sql    = "SELECT usr_first_name, usr_last_name, usr_email FROM ". TBL_USERS. " WHERE usr_id = {0} ";
    $sql    = prepareSQL($sql, array($_GET['usr_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $row = mysql_fetch_row($result);

    //den gefundenen User dem Mailobjekt hinzufuegen...
    $email->addRecipient($row[2], "$row[0] $row[1]");
}
else
{
    $rolle = null;

    //Rolle wurde uebergeben, dann an alle Mitglieder aus der DB fischen
    $sql    = "SELECT usr_first_name, usr_last_name, usr_email, rol_name
                FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
               WHERE rol_org_shortname = '$g_organization'
                 AND rol_id            = {0}
                 AND mem_rol_id        = rol_id
                 AND mem_valid         = 1
                 AND mem_usr_id        = usr_id
                 AND usr_valid         = 1
                 AND LENGTH(usr_email) > 0 ";
    $sql    = prepareSQL($sql, array($_POST['rol_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);

    while ($row = mysql_fetch_object($result))
    {
        $email->addBlindCopy($row->usr_email, "$row->usr_first_name $row->usr_last_name");
        $rolle = $row->rol_name;
    }

    //Falls in der Rolle kein User mit gueltiger Mailadresse oder die Rolle gar nicht in der Orga
    // existiert, muss zumindest eine brauchbare Fehlermeldung präsentiert werden...
    if (is_null($rolle))
    {
        $g_message->show("role_empty");
    }

}

// Falls eine Kopie benoetigt wird, das entsprechende Flag im Mailobjekt setzen
if ($_POST['kopie'])
{
    $email->setCopyToSenderFlag();

    //Falls der User eingeloggt ist, werden die Empfaenger der Mail in der Kopie aufgelistet
    if ($g_session_valid)
    {
        $email->setListRecipientsFlag();
    }
}

//Den Text fuer die Mail aufbereiten
$mail_body = $_POST['name']. " hat ";
if (strlen($rolle) > 0)
{
    $mail_body = $mail_body. "an die Rolle \"$rolle\"";
}
else
{
    $mail_body = $mail_body. "Dir";
}
$mail_body = $mail_body. " von $g_current_organization->homepage folgende E-Mail geschickt:\n";
$mail_body = $mail_body. "Eine Antwort kannst Du an ". $_POST['mailfrom']. " schicken.";

if (!$g_session_valid)
{
    $mail_body = $mail_body. utf8_decode("\n(Der Absender war nicht eingeloggt. Deshalb könnten die Absenderangaben fehlerhaft sein.)");
}
$mail_body = $mail_body. "\n\n\n". $_POST['body'];

//Den Text an das Mailobjekt uebergeben
$email->setText($mail_body);


//Nun kann die Mail endgueltig versendet werden...
if ($email->sendEmail())
{
    if (strlen($_POST['rol_id']) > 0)
    {
        $err_text = "die Rolle $rolle";
    }
    else
    {
        $err_text = $_POST['mailto'];
    }
    $err_code="mail_send";

    // Der CaptchaCode wird bei erfolgreichem Mailversand aus der Session geloescht
    if (isset($_SESSION['captchacode']))
    {
        unset($_SESSION['captchacode']);
    }

    // Bei erfolgreichem Versenden wird aus dem NaviObjekt die am Anfang hinzugefuegte URL wieder geloescht...
    $_SESSION['navigation']->deleteLastUrl();
}
else
{
    if (strlen($_POST['rol_id']) > 0)
    {
        $err_text = "die Rolle $rolle";
    }
    else
    {
        $err_text = $_POST['mailto'];
    }
    $err_code="mail_not_send";
}


$g_message->show($err_code, $err_text);
?>