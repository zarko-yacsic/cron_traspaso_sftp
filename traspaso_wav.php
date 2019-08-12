<?php
date_default_timezone_set ('America/Santiago');

// Credenciales DB...
$db_host            = 'localhost';
$db_name            = 'db_zyacsic';
$db_user            = 'dev_zyacsic';
$db_pass            = 'WfpB9@C2';

// Credenciales SFTP Origen...
$sftp_origen_host   = '13.52.94.83';
$sftp_origen_user   = 'cron_audios';
$sftp_origen_pass   = '$8ESzk2M92';

// Credenciales SFTP Destino...
$sftp_final_host    = '127.0.0.1';
$sftp_final_user    = 'gls_audios';
$sftp_final_pass    = '_gQKd6+K';


function enviar_notificacion($tipo_notificacion, $item_conexion, $registros = ''){
    $asunto         = 'Notificación: traspaso_wav.php';
    $enviar_mail    = 'agonzalez@tga.cl,soporte-tecnologia@tga.cl';
    $enviar_nombre  = 'Soporte TGA';
    $substr_tipo    = substr($tipo_notificacion, 0, 9);

    // Cuando '$item_conexion' es del tipo 'nombre_bd|nombre_tabla'...
    if(strpos($item_conexion, '|') !== false){
        $tmp = explode('|', $item_conexion);
        $item_conexion = $tmp;
    }

    switch($substr_tipo){
        case 'ERR_CONEX':
            switch($tipo_notificacion){
                case 'ERR_CONEXION_BD':
                    $titulo         = 'Error en conexión a base de datos';
                    $descripcion    = 'Verificar la conexión a base de datos <b>\'' . $item_conexion[0] . '\'</b>.';
                    break;
                case 'ERR_CONEXION_SFTP_ORIGEN':
                    $titulo         = 'Error en conexión a SFTP de origen';
                    $descripcion    = 'Verificar la conexión a SFTP de origen <b>' . $item_conexion . '</b>.';
                    break;
                case 'ERR_CONEXION_SFTP_FINAL':
                    $titulo         = 'Error en conexión a SFTP de destino';
                    $descripcion    = 'Verificar la conexión a SFTP de destino <b>' . $item_conexion . '</b>.';
                    break;
            }
            break;
        case 'ERR_FILES':
            switch($tipo_notificacion){
                case 'ERR_FILES_TRASPASO_SFTP':
                    $replace_str   = 'Error al copiar el archivo a SFTP de destino';
                    break;
            }
            $titulo         = 'Error al copiar el archivo a SFTP de destino <b>\'' . $item_conexion . '\'</b>.';
            $descripcion    = '';
            if($registros != ''){
                $registros_ids = explode(',', $registros);
                for($r=0; $r < count($registros_ids); $r++){
                    $descripcion .= str_replace('[DESCRIPCION_ERROR]', $replace_str, '[DESCRIPCION_ERROR], $id=' . $registros_ids[$r] . '<br>');
                }
            }
            break;
        case 'ERR_QUERY':
            switch($tipo_notificacion){
                case 'ERR_QUERY_TRASPASO_OK':
                    $replace_str   = 'Error al marcar el traspaso correcto';
                    break;
                case 'ERR_QUERY_TRASPASO_FAIL':
                    $replace_str   = 'Error al marcar el error de traspaso';
                    break;
                case 'ERR_QUERY_ARCHIVO_EXISTE':
                    $replace_str   = 'Error al marcar cuando audio ya existe';
                    break;
            }
            
            $titulo         = 'Error al actualizar tabla <b>\'' . $item_conexion[1] . '\'</b>.';
            $descripcion    = '';
            if($registros != ''){
                $registros_ids = explode(',', $registros);
                for($r=0; $r < count($registros_ids); $r++){
                    $descripcion .= str_replace('[DESCRIPCION_ERROR]', $replace_str, '[DESCRIPCION_ERROR], $id=' . $registros_ids[$r] . '<br>');
                }
            }
            break;
    }

    $mensaje        = '<!-- <html>
                        <head>
                          <title>' . $titulo . '</title>
                        </head>
                        <body> -->
                          <table border="0" width="530" border="0" cellspacing="0" cellpadding="5">
                            <tbody>
                                <tr>
                                  <td colspan="2" valign="top"><p>' . $titulo . '</p></td>
                                </tr>
                                <tr>
                                  <td nowrap="nowrap" valign="top" width="90">Descripción :</td>
                                  <td nowrap="nowrap" valign="top" width="420">' . $descripcion . '</td>
                                </tr>
                                <tr>
                                  <td nowrap="nowrap" valign="top">Fecha :</td>
                                  <td nowrap="nowrap" valign="top"><strong>' . date('d-m-Y H:i:s') . '</strong></td>
                                </tr>
                            </tbody>
                          </table>
                        <!-- </body>
                        </html> -->';

    $headers        = 'From: dba@alertastga.cl' . "\r\n" .
                      'Reply-To: dba@alertastga.cl' . "\r\n" .
                      'X-Mailer: PHP/' . phpversion()."\r\n".
                      'MIME-Version: 1.0'."\r\n".
                      'Content-Type: text/html; charset=utf-8';

    if(!mail($enviar_mail, $asunto, $mensaje, $headers)){
        echo 'Error al enviar notificación por correo.<br>';
    }
}



// Validar acceso a BD...
$db_conn    = new mysqli($db_host, $db_user, $db_pass, $db_name, 3306);
if($db_conn->connect_errno){
    enviar_notificacion('ERR_CONEXION_BD', $db_name . '|clickfono_leads');
    exit('Error de conexión a BD ' . $db_name . '.');
}
else{
    set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib');
    set_time_limit(0);
    include('Net/SFTP.php');

    // Conectar a SFTP de origen...
    $sftp_origen = new Net_SFTP($sftp_origen_host, 10422);
    if(!$sftp_origen->login($sftp_origen_user, $sftp_origen_pass)){
        enviar_notificacion('ERR_CONEXION_SFTP_ORIGEN', $sftp_origen_host);
        exit('Falló la conexión al SFTP de origen.');
    }

    // Conectar a SFTP de destino...
    $sftp_final = new Net_SFTP($sftp_final_host, 22);
    if (!$sftp_final->login($sftp_final_user, $sftp_final_pass)){
        enviar_notificacion('ERR_CONEXION_SFTP_FINAL', $sftp_final_host);
        exit('Falló la conexión al SFTP de destino.');
    }

    // Directorios...
    $sftp_origen_dir_base               = 'web/';
    $sftp_final_dir_base                = 'web/oidua/';

    // Posibles errores...
    $err_stfp_traspaso                  = false;
    $err_query_traspaso_ok              = false;
    $err_query_traspaso_fail            = false;
    $err_query_archivo_existe           = false;

    // String con ids registros errores...
    $err_stfp_traspaso_ids              = '';
    $err_query_traspaso_ok_ids          = '';
    $err_query_traspaso_fail_ids        = '';
    $err_query_archivo_existe_ids       = '';

    // Todas las conexiones OK, entonces seleccionamos audios desde BD... 
    $result = $db_conn->query("SELECT id
                                    , uuid
                                    , idInmobiliaria
                                    , idProyecto
                                    , nombre
                                    , apellido
                                    , email
                                    , idEmailUnico
                                    , rutaAudio
                                    , nombreAudioBase
                                    , nombreAudioFinal   
                                 FROM clickfono_leads 
                                WHERE traspasoAudio     = 1 
                                  AND rutaAudio         != '' 
                                  AND nombreAudioBase   != ''
                             ORDER BY fechaIngreso ASC;");
    $total_bd   = mysqli_num_rows($result);
    $total_wav  = 0;

    while ($row = mysqli_fetch_array($result)){
        $id                 = $row['id'];
        $idInmobiliaria     = $row['idInmobiliaria'];
        $idProyecto         = $row['idProyecto'];
        $idEmailUnico       = $row['idEmailUnico'];
        $sftp_origen_dir    = $row['rutaAudio'];
        $sftp_final_dir     = str_replace($sftp_origen_dir_base, $sftp_final_dir_base, $row['rutaAudio']);
        $audio_base         = $row['nombreAudioBase'];
        $audio_nuevo_md5    = md5($idInmobiliaria . $idProyecto + $id + $idEmailUnico + 'pikachu') . '.wav';

        // Crear directorio de destino en caso de no existir...
        if (!$sftp_final->file_exists($sftp_final_dir)){
            $dir_inmobiliaria = 'i' . $idInmobiliaria;
            $sftp_final->chdir('./' . $sftp_final_dir_base);
            $sftp_final->mkdir($dir_inmobiliaria, 0775);
        }

        // Posicionarse en directorio final en SFTP destino...
        $sftp_final->chdir($sftp_final_dir);

        // Verificar si existe archivo en SFTP de destino. Si no existe, realizar traspaso hacia SFTP destino...
        if($sftp_final->file_exists($audio_nuevo_md5)){
            $update = $db_conn->query("UPDATE clickfono_leads 
                                           SET traspasoAudio = 4 
                                         WHERE id = $id LIMIT 1;"); // <-- Cambiar 'traspasoAudio' a archivo ya existente...
            if(!$update){
                $err_query_archivo_existe       = true;
                $err_query_archivo_existe_ids  .= $id . ',';
            }
        }
        else{
            $sftp_origen->chdir('./' . $sftp_origen_dir); // <-- Posicionarse en directorio SFTP origen...
            if($sftp_final->put($audio_nuevo_md5, $sftp_origen->get($audio_base))){ // <-- Copia exitosa...
                $update = $db_conn->query("UPDATE clickfono_leads 
                                              SET traspasoAudio = 2, 
                                                  nombreAudioFinal = '$audio_nuevo_md5' 
                                            WHERE id = $id LIMIT 1;"); // <-- Cambiar 'traspasoAudio' a exitoso y guardar 'nombreAudioFinal'...
                if(!$update){
                    $err_query_traspaso_ok       = true;
                    $err_query_traspaso_ok_ids  .= $id . ',';
                }
                else{
                    $total_wav++;
                }
            }
            
            else{ // <-- Copia fallida...
                $err_stfp_traspaso              = true;
                $err_stfp_traspaso_ids         .= $id . ',';
                $update = $db_conn->query("UPDATE clickfono_leads 
                                              SET traspasoAudio = 3 
                                            WHERE id = $id LIMIT 1;"); // <-- Cambiar 'traspasoAudio' a error...
                if($update){
                    $err_query_traspaso_fail        = true;
                    $err_query_traspaso_fail_ids   .= $id . ',';
                }
            }
        }
    }

    if(strrpos($err_stfp_traspaso_ids, ',') !== false){
        $err_stfp_traspaso_ids = substr($err_stfp_traspaso_ids, 0, -1);
    }
    if(strrpos($err_query_traspaso_ok_ids, ',') !== false){
        $err_query_traspaso_ok_ids = substr($err_query_traspaso_ok_ids, 0, -1);
    }
    if(strrpos($err_query_traspaso_fail_ids, ',') !== false){
        $err_query_traspaso_fail_ids = substr($err_query_traspaso_fail_ids, 0, -1);
    }
    if(strrpos($err_query_archivo_existe_ids, ',') !== false){
        $err_query_archivo_existe_ids = substr($err_query_archivo_existe_ids, 0, -1);
    }

    if($err_stfp_traspaso === true){
        enviar_notificacion('ERR_FILES_TRASPASO_SFTP', $sftp_final_host, $err_stfp_traspaso_ids);
    }
    if($err_query_traspaso_ok == true){
        enviar_notificacion('ERR_QUERY_TRASPASO_OK', $db_name . '|clickfono_leads', $err_query_traspaso_ok_ids);
    }
    if($err_query_traspaso_fail == true){
        enviar_notificacion('ERR_QUERY_TRASPASO_FAIL', $db_name . '|clickfono_leads', $err_query_traspaso_fail_ids);
    }
    if($err_query_archivo_existe == true){
        enviar_notificacion('ERR_QUERY_ARCHIVO_EXISTE', $db_name . '|clickfono_leads', $err_query_archivo_existe_ids);
    }

    // Cerrar conexiones SFTP...
    $sftp_origen->disconnect();
    $sftp_final->disconnect();
}

// Cerrar conexion a BD...
mysqli_close($db_conn);
?>