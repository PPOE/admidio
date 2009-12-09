<?php
/******************************************************************************
 * Login Form
 *
 * Version 1.3.0
 *
 * Login Form stellt das Loginformular mit den entsprechenden Feldern dar,
 * damit sich ein Benutzer anmelden kann. Ist der Benutzer angemeldet, so
 * werden an der Stelle der Felder nun nützliche Informationen des Benutzers
 * angezeigt.
 *
 * Kompatible ab Admidio-Versions 2.1.0
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Pfad des Plugins ermitteln
$plugin_folder_pos = strpos(__FILE__, "adm_plugins") + 11;
$plugin_file_pos   = strpos(__FILE__, "login_form.php");
$plugin_folder     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}
require_once(PLUGIN_PATH. "/../adm_program/system/common.php");
require_once(SERVER_PATH. "/adm_program/system/classes/table_roles.php");
require_once(PLUGIN_PATH. "/$plugin_folder/config.php");
 
// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_show_register_link) == false || is_numeric($plg_show_register_link) == false)
{
    $plg_show_register_link = 1;
}

if(isset($plg_show_email_link) == false || is_numeric($plg_show_email_link) == false)
{
    $plg_show_email_link = 1;
}

if(isset($plg_show_logout_link) == false || is_numeric($plg_show_logout_link) == false)
{
    $plg_show_logout_link = 1;
}

if(isset($plg_show_icons) == false || is_numeric($plg_show_icons) == false)
{
    $plg_show_icons = 1;
}

if(isset($plg_link_target) && $plg_link_target != "_self")
{
    $plg_link_target = ' target="'. strip_tags($plg_link_target). '" ';
}
else
{
    $plg_link_target = "";
}

if(isset($plg_rank) == false)
{
    $plg_rank = array();
}

// DB auf Admidio setzen, da evtl. noch andere DBs beim User laufen
$g_db->setCurrentDB();

$plg_icon_code = "";

if($g_valid_login == 1)
{
    echo '    
    <script type="text/javascript">
        function loadPageLogout()
        {';
            if(strlen($plg_link_target) > 0 && strpos($plg_link_target, "_") === false)
            {
                echo '
                parent.'. $plg_link_target. '.location.href = \''. $g_root_path. '/adm_program/system/logout.php\';
                self.location.reload(); ';
            }
            else
            {
                echo 'self.location.href = \''. $g_root_path. '/adm_program/system/logout.php\';';
            }
        echo '
        }
    </script>
    
    <ul class="formFieldList" id="plgLoginFormFieldList">
        <li>
            <dl>
                <dt>Benutzer:</dt>
                <dd>
                    <a href="'. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $g_current_user->getValue("usr_id"). '" 
                    '. $plg_link_target. ' title="Profil aufrufen">'. $g_current_user->getValue("Vorname"). ' '. $g_current_user->getValue("Nachname"). '</a>
                </dd>
            </dl>
        </li>
        <li>
            <dl>
                <dt>Aktiv seit:</dt>
                <dd>'. mysqldatetime("h:i", $g_current_session->getValue("ses_begin")). ' Uhr</dd>
            </dl>
        </li>
        <li>
            <dl>
                <dt>Anzahl Logins:</dt>
                <dd>'. $g_current_user->getValue("usr_number_login");
    
                    // Zeigt einen Rank des Benutzers an, sofern diese in der config.php hinterlegt sind
                    if(count($plg_rank) > 0)
                    {
                        $rank  = "";
                        $value = reset($plg_rank);

                        while($value != false)
                        {
                            $count_rank = key($plg_rank);
                            if($count_rank < $g_current_user->getValue("usr_number_login"))
                            {
                                $rank = strip_tags($value);
                            }
                            $value = next($plg_rank);
                        }

                        if(strlen($rank) > 0)
                        {
                            echo "&nbsp;$rank";
                        }
                    }
                echo "</dd>
            </dl>
        </li>";
    
        // Link zum Ausloggen
        if($plg_show_logout_link)
        {
            if($plg_show_icons)
            {
                $plg_icon_code = '<a href="javascript:loadPageLogout()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="Logout" /></a>';
            }
            echo '<li>
                <dl>
                    <dt class="iconTextLink">'. $plg_icon_code. '
                        <a href="javascript:loadPageLogout()">Logout</a>
                    </dt>
                </dl>
            </li>';
        }
    echo "</ul>";
}
else
{
    // Login-Formular
    echo '
    <form id="plugin_'. $plugin_folder. '" style="display: inline;" action="'. $g_root_path. '/adm_program/system/login_check.php" method="post">
        <ul class="formFieldList" id="plgLoginFormFieldList">
            <li>
                <dl>
                    <dt><label for="plg_usr_login_name">Benutzername:</label></dt>
                    <dd><input type="text" id="plg_usr_login_name" name="plg_usr_login_name" size="10" maxlength="35" tabindex="95" /></dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="plg_usr_password">Passwort:</label></dt>
                    <dd><input type="password" id="plg_usr_password" name="plg_usr_password" size="10" maxlength="25" tabindex="96" /></dd>
                </dl>
            </li>';
            
            if($g_preferences['enable_auto_login'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt><label for="plg_auto_login">Angemeldet bleiben:</label></dt>
                        <dd><input type="checkbox" id="plg_auto_login" name="plg_auto_login" value="1" tabindex="97" /></dd>
                    </dl>
                </li>';
            } 
            
            if($plg_show_icons)
            {
                $plg_icon_code = '<img src="'. THEME_PATH. '/icons/key.png" alt="Login" />&nbsp;';
            }
            echo '
            <li id="plgRowLoginButton">
                <dl>
                    <dt>
                        <button type="submit" value="Login" tabindex="98">'.$plg_icon_code.'Login</button>
                    </dt>
                    <dd>&nbsp;</dd>
                </dl>
            </li>';
        
            // Links zum Registrieren und melden eines Problems anzeigen, falls gewuenscht
            if($plg_show_register_link || $plg_show_email_link)
            {
                echo '<li>
                    <dl>';
                        if($plg_show_register_link && $g_preferences['registration_mode'])
                        {
                            if($plg_show_icons)
                            {
                                $plg_icon_code = '<span class="iconTextLink">
                                    <a href="'. $g_root_path. '/adm_program/system/registration.php"><img src="'. THEME_PATH. '/icons/new_registrations.png" alt="Registrierung" /></a>
                                    <a href="'. $g_root_path. '/adm_program/system/registration.php" '. $plg_link_target. '>Registrieren</a>
                                </span>';
                            }
                            else
                            {
                                $plg_icon_code = '<a href="'. $g_root_path. '/adm_program/system/registration.php" '. $plg_link_target. '>Registrieren</a>';
                            }
                            echo '<dt>'.$plg_icon_code.'</dt>
                                <dd>&nbsp;</dd>';
                        }
                        if($plg_show_register_link && $plg_show_email_link)
                        {
                            echo '</dl></li><li><dl>';
                        }
                        if($plg_show_email_link)
                        {
                            // Rollenobjekt fuer 'Webmaster' anlegen
                            $role_webmaster = new TableRoles($g_db, 'Webmaster');

                            // Link bei Loginproblemen
                            if($g_preferences['enable_password_recovery'] == 1
                            && $g_preferences['enable_system_mails'] == 1)
                            {
                                // neues Passwort zusenden
                                $mail_link = "$g_root_path/adm_program/system/lost_password.php";
                            }
                            elseif($g_preferences['enable_mail_module'] == 1 
                            && $role_webmaster->getValue("rol_mail_this_role") == 3)
                            {
                                // Mailmodul aufrufen mit Webmaster als Ansprechpartner
                                $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?rol_id=". $role_webmaster->getValue("rol_id"). "&amp;subject=Loginprobleme";
                            }
                            else
                            {
                                // direkte Mail an den Webmaster ueber einen externen Mailclient
                                $mail_link = "mailto:". $g_preferences['email_administrator']. "?subject=Loginprobleme";
                            }

                            if($plg_show_icons)
                            {
                                $plg_icon_code = '<span class="iconTextLink">
                                    <a href="'. $mail_link. '"><img src="'. THEME_PATH. '/icons/email_key.png" alt="Loginprobleme" /></a>
                                    <a href="'. $mail_link. '" '. $plg_link_target. '>Loginprobleme</a>
                                </span>';
                            }
                            else
                            {
                                $plg_icon_code = '<a href="'. $mail_link. '" '. $plg_link_target. '>Loginprobleme</a>';
                            }
                            
                            echo '<dt>'.$plg_icon_code.'</dt>
                            <dd>&nbsp;</dd>';
                        }
                    echo '</dl>
                </li>';
            }    
        echo '</ul>
    </form>';   
}

?>