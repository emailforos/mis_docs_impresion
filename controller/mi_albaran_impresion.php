<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

//require_once __DIR__.'/../extras/fs_pdf.php';

require_once 'plugins/mis_docs_impresion/fpdf17/fs_fp_fpdf.php';

require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';
require_model('cliente.php');
require_model('cuenta_banco.php');
require_model('cuenta_banco_cliente.php');
require_model('forma_pago.php');
//Añadidas
require_model('pais.php');
require_model('agencia_transporte.php');


/**
 * Esta clase agrupa los procedimientos de imprimir/enviar albaranes y facturas.
 */
class mi_albaran_impresion extends fs_controller
{
   public $albaran;
   public $cliente;
   public $factura;
   public $impresion;
   public $impuesto;
   
   private $numpaginas;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'imprimir', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->albaran = FALSE;
      $this->cliente = FALSE;
      $this->factura = FALSE;
      $this->impuesto = new impuesto();
      
      /// obtenemos los datos de configuración de impresión
      $this->impresion = array(
          'print_ref' => '1',
          'print_dto' => '1',
          'print_alb' => '0',
          'print_formapago' => '1'
      );
      $fsvar = new fs_var();
      $this->impresion = $fsvar->array_get($this->impresion, FALSE);
      
      if( isset($_REQUEST['albaran']) AND isset($_REQUEST['id']) )
      {
         $alb = new albaran_cliente();
         $this->albaran = $alb->get($_REQUEST['id']);
         if($this->albaran)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->albaran->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('albaran');
         }
         else
            $this->generar_pdf_albaran();
      }
      else if( isset($_REQUEST['factura']) AND isset($_REQUEST['id']) )
      {
         $fac = new factura_cliente();
         $this->factura = $fac->get($_REQUEST['id']);
         if($this->factura)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->factura->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('factura', $_REQUEST['tipo']);
         }
         else
            $this->generar_pdf_factura($_REQUEST['tipo']);
      }
      
      $this->share_extensions();
   }
   
   private function share_extensions()
   {
      $extensiones = array(
          array(
              'name' => 'imprimir_albaran',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_albaran',
              'type' => 'pdf',
              'text' => 'Imprimir ALBARAN valorado',
              'params' => '&albaran=TRUE'
          ),
          array(
              'name' => 'imprimir_albaran_noval',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_albaran',
              'type' => 'pdf',
              'text' => 'Imprimir ALBARAN no valorado',
              'params' => '&albaran=TRUE&noval=TRUE'
          ),
          array(
              'name' => 'email_albaran',
              'page_from' => __CLASS__,
              'page_to' => 'ventas_albaran',
              'type' => 'email',
              'text' => ucfirst(FS_ALBARAN).' simple',
              'params' => '&albaran=TRUE'
          )
      );
      foreach($extensiones as $ext)
      {
         $fsext = new fs_extension($ext);
         if( !$fsext->save() )
         {
            $this->new_error_msg('Error al guardar la extensión '.$ext['name']);
         }
      }
   }
   
   private function generar_pdf_lineas(&$pdf_doc, &$lineas, &$linea_actual, $lppag, $documento)
   {
      /// calculamos el número de páginas
      if( !isset($this->numpaginas) )
      {
         $this->numpaginas = 0;
         $linea_a = 0;
         while( $linea_a < count($lineas) )
         {
            $lppag2 = $lppag;
            foreach($lineas as $i => $lin)
            {
               if($i >= $linea_a AND $i < $linea_a + $lppag2)
               {
                  $linea_size = 1;
                  $len = mb_strlen($lin->referencia.' '.$lin->descripcion);
                  while($len > 85)
                  {
                     $len -= 85;
                     $linea_size += 0.5;
                  }
                  
                  $aux = explode("\n", $lin->descripcion);
                  if( count($aux) > 1 )
                  {
                     $linea_size += 0.5 * ( count($aux) - 1);
                  }
                  
                  if($linea_size > 1)
                  {
                     $lppag2 -= $linea_size - 1;
                  }
               }
            }
            
            $linea_a += $lppag2;
            $this->numpaginas++;
         }
         
         if($this->numpaginas == 0)
         {
            $this->numpaginas = 1;
         }
      }
      
      if($this->impresion['print_dto'])
      {
         $this->impresion['print_dto'] = FALSE;
         
         /// leemos las líneas para ver si de verdad mostramos los descuentos
         foreach($lineas as $lin)
         {
            if($lin->dtopor != 0)
            {
               $this->impresion['print_dto'] = TRUE;
               break;
            }
         }
      }
      
      $dec_cantidad = 0;
      $multi_iva = FALSE;
      $multi_re = FALSE;
      $multi_irpf = FALSE;
      $iva = FALSE;
      $re = FALSE;
      $irpf = FALSE;
      /// leemos las líneas para ver si hay que mostrar los tipos de iva, re o irpf
      foreach($lineas as $i => $lin)
      {
         if( $lin->cantidad != intval($lin->cantidad) )
         {
            $dec_cantidad = 2;
         }
         
         if($iva === FALSE)
         {
            $iva = $lin->iva;
         }
         else if($lin->iva != $iva)
         {
            $multi_iva = TRUE;
         }
         
         if($re === FALSE)
         {
            $re = $lin->recargo;
         }
         else if($lin->recargo != $re)
         {
            $multi_re = TRUE;
         }
         
         if($irpf === FALSE)
         {
            $irpf = $lin->irpf;
         }
         else if($lin->irpf != $irpf)
         {
            $multi_irpf = TRUE;
         }
         
         /// restamos líneas al documento en función del tamaño de la descripción
         if($i >= $linea_actual AND $i < $linea_actual+$lppag)
         {
            $linea_size = 1;
            $len = mb_strlen($lin->referencia.' '.$lin->descripcion);
            while($len > 85)
            {
               $len -= 85;
               $linea_size += 0.5;
            }
            
            $aux = explode("\n", $lin->descripcion);
            if( count($aux) > 1 )
            {
               $linea_size += 0.5 * ( count($aux) - 1);
            }
            
            if($linea_size > 1)
            {
               $lppag -= $linea_size - 1;
            }
         }
      }
      
      /*
       * Creamos la tabla con las lineas del documento
       */
      $pdf_doc->new_table();
      $table_header = array(
          'alb' => '<b>'.ucfirst(FS_ALBARAN).'</b>',
          'descripcion' => '<b>Ref. + Descripción</b>',
          'cantidad' => '<b>Cant.</b>',
          'pvp' => '<b>PVP</b>',
      );
      
      /// ¿Desactivamos la columna de albaran?
      if( get_class_name($documento) == 'factura_cliente' )
      {
         if($this->impresion['print_alb'])
         {
            /// aunque esté activada, si la factura no viene de un albaran, la desactivamos
            $this->impresion['print_alb'] = FALSE;
            foreach($lineas as $lin)
            {
               if($lin->idalbaran)
               {
                  $this->impresion['print_alb'] = TRUE;
                  break;
               }
            }
         }
         
         if( !$this->impresion['print_alb'] )
         {
            unset($table_header['alb']);
         }
      }
      else
      {
         unset($table_header['alb']);
      }
      
      if($this->impresion['print_dto'] AND !isset($_GET['noval']) )
      {
         $table_header['dto'] = '<b>Dto.</b>';
      }
      
      if($multi_iva AND !isset($_GET['noval']) )
      {
         $table_header['iva'] = '<b>'.FS_IVA.'</b>';
      }
      
      if($multi_re AND !isset($_GET['noval']) )
      {
         $table_header['re'] = '<b>R.E.</b>';
      }
      
      if($multi_irpf AND !isset($_GET['noval']) )
      {
         $table_header['irpf'] = '<b>'.FS_IRPF.'</b>';
      }
      
      if( isset($_GET['noval']) )
      {
         unset($table_header['pvp']);
      }
      else
      {
         $table_header['importe'] = '<b>Importe</b>';
      }
      
      $pdf_doc->add_table_header($table_header);
      
      for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < count($lineas)));)
      {
         $descripcion = $pdf_doc->fix_html($lineas[$linea_actual]->descripcion);
         if( !is_null($lineas[$linea_actual]->referencia) )
         {
            $descripcion = '<b>'.$lineas[$linea_actual]->referencia.'</b> '.$descripcion;
         }
         
         $fila = array(
             'alb' => '-',
             'cantidad' => $this->show_numero($lineas[$linea_actual]->cantidad, $dec_cantidad),
             'descripcion' => $descripcion,
             'pvp' => $this->show_precio($lineas[$linea_actual]->pvpunitario, $documento->coddivisa, TRUE, FS_NF0_ART),
             'dto' => $this->show_numero($lineas[$linea_actual]->dtopor) . " %",
             'iva' => $this->show_numero($lineas[$linea_actual]->iva) . " %",
             're' => $this->show_numero($lineas[$linea_actual]->recargo) . " %",
             'irpf' => $this->show_numero($lineas[$linea_actual]->irpf) . " %",
             'importe' => $this->show_precio($lineas[$linea_actual]->pvptotal, $documento->coddivisa)
         );
         
         if($lineas[$linea_actual]->dtopor == 0)
         {
            $fila['dto'] = '';
         }
         
         if($lineas[$linea_actual]->recargo == 0)
         {
            $fila['re'] = '';
         }
         
         if($lineas[$linea_actual]->irpf == 0)
         {
            $fila['irpf'] = '';
         }
         
         if( get_class_name($lineas[$linea_actual]) == 'linea_factura_cliente' AND $this->impresion['print_alb'] )
         {
            $fila['alb'] = $lineas[$linea_actual]->albaran_numero();
         }
         
         $pdf_doc->add_table_row($fila);
         $linea_actual++;
      }
      
      $pdf_doc->save_table(
              array(
                  'fontSize' => 8,
                  'cols' => array(
                      'cantidad' => array('justification' => 'right'),
                      'pvp' => array('justification' => 'right'),
                      'dto' => array('justification' => 'right'),
                      'iva' => array('justification' => 'right'),
                      're' => array('justification' => 'right'),
                      'irpf' => array('justification' => 'right'),
                      'importe' => array('justification' => 'right')
                  ),
                  'width' => 520,
                  'shaded' => 1,
                  'shadeCol' => array(0.95, 0.95, 0.95),
                  'lineCol' => array(0.3, 0.3, 0.3),
              )
      );
   }
   
   private function generar_pdf_albaran($archivo = FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      /// Creamos el PDF y escribimos sus metadatos
      ob_end_clean();
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $lineas = $this->albaran->get_lineas();

      $pdf_doc->SetTitle('Albaran: '. $this->albaran->codigo);
      $pdf_doc->SetSubject('Cliente: ' . $this->albaran->nombrecliente);
      $pdf_doc->SetAuthor($this->empresa->nombre);
      $pdf_doc->SetCreator('FacturaSctipts V_' . $this->version());

      $pdf_doc->Open();
      $pdf_doc->AliasNbPages();
      $pdf_doc->SetAutoPageBreak(true, 40);

      // Definimos el color de relleno (gris, rojo, verde, azul)
      $pdf_doc->SetColorRelleno('gris');
         
      /// Definimos todos los datos de la cabecera del presupuesto
      /// Datos de la empresa
      $pdf_doc->fde_nombre = $this->empresa->nombre;
      $pdf_doc->fde_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fde_cifnif = $this->empresa->cifnif;
      $pdf_doc->fde_direccion = $this->empresa->direccion;
      $pdf_doc->fde_codpostal = $this->empresa->codpostal;
      $pdf_doc->fde_ciudad = $this->empresa->ciudad;
      $pdf_doc->fde_provincia = $this->empresa->provincia;
      $pdf_doc->fde_telefono = 'Teléfono: ' . $this->empresa->telefono;
      $pdf_doc->fde_fax = 'Fax: ' . $this->empresa->fax;
      $pdf_doc->fde_email = $this->empresa->email;
      $pdf_doc->fde_web = $this->empresa->web;
      $pdf_doc->fde_piefactura = $this->empresa->pie_factura;
      
      /// Insertamos el Logo y Marca de Agua
      if( file_exists(FS_MYDOCS.'images/logo.png') OR file_exists(FS_MYDOCS.'images/logo.jpg') )
      {
         $pdf_doc->fdf_verlogotipo = '1'; // 1/0 --> Mostrar Logotipo
         $pdf_doc->fdf_Xlogotipo = '10'; // Valor X para Logotipo
         $pdf_doc->fdf_Ylogotipo = '10'; // Valor Y para Logotipo
         $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
         $pdf_doc->fdf_Xmarcaagua = '25'; // Valor X para Marca de Agua
         $pdf_doc->fdf_Ymarcaagua = '110'; // Valor Y para Marca de Agua
      }
      else
      {
         $pdf_doc->fdf_verlogotipo = '0';
         $pdf_doc->fdf_Xlogotipo = '0';
         $pdf_doc->fdf_Ylogotipo = '0';
         $pdf_doc->fdf_vermarcaagua = '0';
         $pdf_doc->fdf_Xmarcaagua = '0';
         $pdf_doc->fdf_Ymarcaagua = '0';
      }
      
      // Tipo de Documento
      $pdf_doc->fdf_tipodocumento = 'Albaran'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->albaran->codigo;
      /*Nosotros no usamos NUMERO2 aqui . " " . $this->factura->numero2*/

      // Fecha, Codigo cliente y observaciones del presupuesto
      $pdf_doc->fdf_fecha = $this->albaran->fecha;
      $pdf_doc->fdf_codcliente = $this->albaran->codcliente;
      $pdf_doc->fdf_observaciones = iconv("UTF-8", "CP1252", $this->fix_html($this->albaran->observaciones));
      $pdf_doc->fdf_numero2 = $this->albaran->numero2;

    
     // Datos del Cliente
      $pdf_doc->fdf_nombrecliente = $this->fix_html($this->albaran->nombrecliente);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->albaran->cifnif;
      $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
      $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
      $pdf_doc->fdc_fax = $this->cliente->fax;
      $pdf_doc->fdc_email = $this->cliente->email;
      if ($this->albaran->envio_direccion){
        if ($this->albaran->envio_nombre!=NULL or $this->albaran->envio_apellidos!=NULL){
            $pdf_doc->fdf_nombrecliente = $this->fix_html($this->albaran->envio_nombre).' '.$this->fix_html($this->albaran->envio_apellidos);
            $pdf_doc->fdf_cifnif = NULL;
            $pdf_doc->fdf_telefono1 = ' ';
            $pdf_doc->fdf_telefono2 = ' ';
            $pdf_doc->fdf_fax = ' ';
            $pdf_doc->fdf_email = ' ';
        }
        $pdf_doc->fdf_direccion = $this->fix_html($this->albaran->envio_direccion);
        $pdf_doc->fdf_codpostal = $this->albaran->envio_codpostal;
        $pdf_doc->fdf_ciudad = $this->albaran->envio_ciudad;
        $pdf_doc->fdf_provincia = $this->albaran->envio_provincia;
      } else {
        $pdf_doc->fdf_direccion = $this->fix_html($this->albaran->direccion);
        $pdf_doc->fdf_codpostal = $this->albaran->codpostal;
        $pdf_doc->fdf_ciudad = $this->albaran->ciudad;
        $pdf_doc->fdf_provincia = $this->albaran->provincia;
      }
      
      // Fecha salida pedido
      $pdf_doc->fdf_salida = '';
      
      $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';
           
      // Forma de Pago del presupuesto
      $formapago = $this->_genera_formapago();
      $pdf_doc->fdf_epago = $formapago;
      
      // Divisa del presupuesto
      $divisa = new divisa();
      $edivisa = $divisa->get($this->albaran->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }

      // Pais del presupuesto
      $pais = new pais();
      $epais = $pais->get($this->albaran->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
      
      // Agencia transporte 
      $agencia = new agencia_transporte();
      $eagencia = $agencia->get($this->albaran->envio_codtrans);
      if ($eagencia) {
         $pdf_doc->fdf_agencia = $eagencia->nombre;
      } else {
          $pdf_doc->fdf_agencia = "SUS MEDIOS";
      }
      
      // Cabecera Titulos Columnas
      $pdf_doc->Setdatoscab(array('ART.','DESCRIPCI'.chr(211).'N', 'CANT', 'PRECIO', 'DTO', 'NETO', 'IMPORTE'));
      $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
      $pdf_doc->SetAligns(array('L','L', 'R', 'R', 'R', 'R', 'R'));
      $pdf_doc->SetColors(array('0|0|0','0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      
      /// Definimos todos los datos del PIE del presupuesto
      /// Lineas de IVA
      $lineas_iva = $this->get_lineas_iva($lineas);
      
      // Version revisada
      if( count($lineas_iva) > 3 )
      {
         $pdf_doc->fdf_lineasiva = $lineas_iva;
      }
      else
      {
         $filaiva = array();
         $totaliva = 0;
         $i = 0;
         foreach($lineas_iva as $li)
         {
            $i++;
            $imp = $this->impuesto->get($li['codimpuesto']);
            $filaiva[$i][0] = $li['codimpuesto'];
            $etemp = round($li['neto'],2);
            $filaiva[$i][1] = ($etemp) ? $this->ckeckEuro($etemp) : '';
            $totaliva = $totaliva + $etemp; 
            $filaiva[$i][2] = $li['iva']. "%";
            $etemp = round($li['totaliva'],2);
            $filaiva[$i][3] = ($etemp) ? $this->ckeckEuro($etemp) : '';
            $totaliva = $totaliva + $etemp;
            $filaiva[$i][4] = $li['recargo']. "%";
            $etemp = round($li['totalrecargo'],2);
            //if ($etemp =="0"){ $filaiva[$i][5] = ("000") ? $this->ckeckEuro("000") : '';}
            //else { $filaiva[$i][5] = ($etemp) ? $this->ckeckEuro($etemp) : ''; }
            $filaiva[$i][5] = ($etemp) ? $this->ckeckEuro($etemp) : '';
            $totaliva = $totaliva + $etemp;
            $filaiva[$i][6] = ''; //// POR CREARRRRRR
            $filaiva[$i][7] = ''; //// POR CREARRRRRR
            $etemp = round($li['totallinea'],2);
            $filaiva[$i][8] = ($etemp) ? $this->ckeckEuro($etemp) : ''; 
            $totaliva = $totaliva + $etemp;
         }
         
         if($filaiva)
         {
            $filaiva[1][6] = $this->albaran->irpf.' %';
            $etemp = round($this->albaran->totalirpf,2);
            $totaliva = $totaliva - $etemp;
            $filaiva[1][7] = ($etemp) ? $this->ckeckEuro($etemp) : '';
         }
         
         $pdf_doc->fdf_lineasiva = $filaiva;
      }
      
      // Total factura numerico
      $etemp = round($this->albaran->total,2);
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($etemp);

      // Total factura numeros a texto
      $pdf_doc->fdf_textotal = $this->albaran->total;


      /// Agregamos la pagina inicial de la factura
      $pdf_doc->AddPage();
      
      // Lineas del presupuesto
      //$lineas = $this->factura->get_lineas();
      
      if ($lineas) {
         $neto = 0;
         $eped = "INICIO";
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            if ($art) {
               $observa = "\n" . utf8_decode( $this->fix_html($art->observaciones) );
            } else {
               // $observa = null; // No mostrar mensaje de error
               $observa = "\n";
            }
            //$eart = utf8_decode( $this->fix_html($art->referencia));
                if ($eped != $this->albaran->numero2){
                    $eped = $this->albaran->numero2;
                    $lafila = array(
                    '0' => "\n" . $lineas[$i]->referencia,
                    '1' => "Su pedido: " . $this->albaran->numero2 . "\n" . utf8_decode($lineas[$i]->descripcion) . $observa,
                    '2' => "\n" . utf8_decode($lineas[$i]->cantidad),
                    '3' => "\n" . $this->ckeckEuro($lineas[$i]->pvpunitario),
                    '4' => "\n" . utf8_decode($lineas[$i]->dtopor),
                    '5' => "\n" . $this->ckeckEuro(($lineas[$i]->pvpunitario)*(1-$lineas[$i]->dtopor/100)),
                    '6' => "\n" . $this->ckeckEuro($lineas[$i]->pvptotal),
                    );
                } else {
                    $lafila = array(
                    '0' => $lineas[$i]->referencia,
                    '1' => utf8_decode($lineas[$i]->descripcion) . $observa,
                    '2' => utf8_decode($lineas[$i]->cantidad),
                    '3' => $this->ckeckEuro($lineas[$i]->pvpunitario),
                    '4' => utf8_decode($lineas[$i]->dtopor),
                    '5' => $this->ckeckEuro(($lineas[$i]->pvpunitario)*(1-$lineas[$i]->dtopor/100)),
                    '6' => $this->ckeckEuro($lineas[$i]->pvptotal),
                    );
                }
            
            $pdf_doc->Row($lafila, '1'); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
         }
         $pdf_doc->piepagina = true;
      }
      
      // Damos salida al archivo PDF
      if ($archivo) {
         if (!file_exists('tmp/' . FS_TMP_NAME . 'enviar')) {
            mkdir('tmp/' . FS_TMP_NAME . 'enviar');
         }

         $pdf_doc->Output('tmp/' . FS_TMP_NAME . 'enviar/' . $archivo, 'F');
      } else {
         $pdf_doc->Output('Albaran '. $this->albaran->codigo . ' ' . $this->fix_html($this->albaran->nombrecliente) . '.pdf','I');
      }
   
   }
   
   private function generar_pdf_factura($tipo = 'simple', $archivo = FALSE)
   {
      if(!$archivo)
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      /// Creamos el PDF y escribimos sus metadatos
      $pdf_doc = new fs_pdf();
      $pdf_doc->pdf->addInfo('Title', ucfirst(FS_FACTURA).' '. $this->factura->codigo);
      $pdf_doc->pdf->addInfo('Subject', ucfirst(FS_FACTURA).' ' . $this->factura->codigo);
      $pdf_doc->pdf->addInfo('Author', $this->empresa->nombre);
      
      $lineas = $this->factura->get_lineas();
      $lineas_iva = $this->get_lineas_iva($lineas);
      if($lineas)
      {
         $linea_actual = 0;
         $pagina = 1;
         
         /// imprimimos las páginas necesarias
         while( $linea_actual < count($lineas) )
         {
            $lppag = 35;
            
            /// salto de página
            if($linea_actual > 0)
            {
               $pdf_doc->pdf->ezNewPage();
            }
            
            /*
             * Creamos la cabecera de la página, en este caso para el modelo carta
             */
            if($tipo == 'carta')
            {
               $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
               
               $direccion = $this->factura->nombrecliente."\n".$this->factura->direccion;
               if($this->factura->apartado)
               {
                  $direccion .= "\n " . ucfirst(FS_APARTADO) . ": " . $this->factura->apartado;
               }
               
               if($this->factura->codpostal)
               {
                  $direccion .= "\n CP: " . $this->factura->codpostal . ' - ';
               }
               else
               {
                  $direccion .= "\n";
               }
               $direccion .= $this->factura->ciudad . "\n(" . $this->factura->provincia . ")";
               
               $pdf_doc->new_table();
               $pdf_doc->add_table_row(
                  array(
                      'campos' => "<b>".ucfirst(FS_FACTURA).":</b>\n<b>Fecha:</b>\n<b>".$this->cliente->tipoidfiscal.":</b>",
                      'factura' => $this->factura->codigo."\n".$this->factura->fecha."\n".$this->factura->cifnif,
                      'cliente' => $pdf_doc->fix_html($direccion)
                  )
               );
               $pdf_doc->save_table(
                  array(
                      'cols' => array(
                          'campos' => array('justification' => 'right', 'width' => 100),
                          'factura' => array('justification' => 'left'),
                          'cliente' => array('justification' => 'right')
                      ),
                      'showLines' => 0,
                      'width' => 520
                  )
               );
               $pdf_doc->pdf->ezText("\n\n\n", 14);
            }
            else /// esta es la cabecera de la página para el modelo 'simple'
            {
               $pdf_doc->generar_pdf_cabecera($this->empresa, $lppag);
               
               /*
                * Esta es la tabla con los datos del cliente:
                * Factura:                 Fecha:
                * Cliente:               CIF/NIF:
                * Dirección:           Teléfonos:
                */
               $pdf_doc->new_table();
               
               if($this->factura->idfacturarect)
               {
                  $pdf_doc->add_table_row(
                     array(
                        'campo1' => "<b>".ucfirst(FS_FACTURA_RECTIFICATIVA).":</b> ",
                        'dato1' => $this->factura->codigo,
                        'campo2' => "<b>Fecha:</b> ".$this->factura->fecha
                     )
                  );
                  $pdf_doc->add_table_row(
                     array(
                        'campo1' => "<b>Original:</b> ",
                        'dato1' => $this->factura->codigorect,
                        'campo2' => ''
                     )
                  );
               }
               else
               {
                  $pdf_doc->add_table_row(
                     array(
                         'campo1' => "<b>".ucfirst(FS_FACTURA).":</b>",
                         'dato1' => $this->factura->codigo,
                         'campo2' => "<b>Fecha:</b> ".$this->factura->fecha
                     )
                  );
               }
               
               $tipoidfiscal = FS_CIFNIF;
               if($this->cliente)
               {
                  $tipoidfiscal = $this->cliente->tipoidfiscal;
               }
               $pdf_doc->add_table_row(
                  array(
                      'campo1' => "<b>Cliente:</b> ",
                      'dato1' => $pdf_doc->fix_html($this->factura->nombrecliente),
                      'campo2' => "<b>".$tipoidfiscal.":</b> ".$this->factura->cifnif
                  )
               );
               
               $direccion = $this->factura->direccion;
               if($this->factura->apartado)
               {
                  $direccion .= ' - '.ucfirst(FS_APARTADO).': '.$this->factura->apartado;
               }
               if($this->factura->codpostal)
               {
                  $direccion .= ' - CP: '.$this->factura->codpostal;
               }
               $direccion .= ' - '.$this->factura->ciudad.' ('.$this->factura->provincia.')';
               $row = array(
                   'campo1' => "<b>Dirección:</b>",
                   'dato1' => $pdf_doc->fix_html($direccion),
                   'campo2' => ''
               );
               
               if(!$this->cliente)
               {
                  /// nada
               }
               else if($this->cliente->telefono1)
               {
                  $row['campo2'] = "<b>Teléfonos:</b> ".$this->cliente->telefono1;
                  if($this->cliente->telefono2)
                  {
                     $row['campo2'] .= "\n".$this->cliente->telefono2;
                     $lppag -= 2;
                  }
               }
               else if($this->cliente->telefono2)
               {
                  $row['campo2'] = "<b>Teléfonos:</b> ".$this->cliente->telefono2;
               }
               $pdf_doc->add_table_row($row);
               
               if($this->empresa->codpais != 'ESP')
               {
                  $pdf_doc->add_table_row(
                     array(
                         'campo1' => "<b>Régimen ".FS_IVA.":</b> ",
                         'dato1' => $this->cliente->regimeniva,
                         'campo2' => ''
                     )
                  );
               }
               
               $pdf_doc->save_table(
                  array(
                      'cols' => array(
                          'campo1' => array('width' => 90, 'justification' => 'right'),
                          'dato1' => array('justification' => 'left'),
                          'campo2' => array('justification' => 'right'),
                      ),
                      'showLines' => 0,
                      'width' => 520,
                      'shaded' => 0
                  )
               );
               $pdf_doc->pdf->ezText("\n", 10);
            }
            
            $this->generar_pdf_lineas($pdf_doc, $lineas, $linea_actual, $lppag, $this->factura);
            
            if( $linea_actual == count($lineas) )
            {
               if($this->factura->observaciones != '')
               {
                  $pdf_doc->pdf->ezText("\n".$pdf_doc->fix_html($this->factura->observaciones), 9);
               }
               
               if( !$this->factura->pagada AND $this->impresion['print_formapago'] )
               {
                  $fp0 = new forma_pago();
                  $forma_pago = $fp0->get($this->factura->codpago);
                  if($forma_pago)
                  {
                     $texto_pago = "\n<b>Forma de pago</b>: ".$forma_pago->descripcion;
                     
                     if($forma_pago->domiciliado)
                     {
                        $cbc0 = new cuenta_banco_cliente();
                        $encontrada = FALSE;
                        foreach($cbc0->all_from_cliente($this->factura->codcliente) as $cbc)
                        {
                           $texto_pago .= "\n<b>Domiciliado en</b>: ";
                           if($cbc->iban)
                           {
                              $texto_pago .= $cbc->iban(TRUE);
                           }
                           
                           if($cbc->swift)
                           {
                              $texto_pago .= "\n<b>SWIFT/BIC</b>: ".$cbc->swift;
                           }
                           $encontrada = TRUE;
                           break;
                        }
                        if(!$encontrada)
                        {
                           $texto_pago .= "\n<b>El cliente no tiene cuenta bancaria asignada.</b>";
                        }
                     }
                     else if($forma_pago->codcuenta)
                     {
                        $cb0 = new cuenta_banco();
                        $cuenta_banco = $cb0->get($forma_pago->codcuenta);
                        if($cuenta_banco)
                        {
                           if($cuenta_banco->iban)
                           {
                              $texto_pago .= "\n<b>IBAN</b>: ".$cuenta_banco->iban(TRUE);
                           }
                           
                           if($cuenta_banco->swift)
                           {
                              $texto_pago .= "\n<b>SWIFT o BIC</b>: ".$cuenta_banco->swift;
                           }
                        }
                     }
                     
                     $texto_pago .= "\n<b>Vencimiento</b>: ".$this->factura->vencimiento;
                     $pdf_doc->pdf->ezText($texto_pago, 9);
                  }
               }
            }
            
            $pdf_doc->set_y(80);
            
            /*
             * Rellenamos la última tabla de la página:
             * 
             * Página            Neto    IVA   Total
             */
            $pdf_doc->new_table();
            $titulo = array('pagina' => '<b>Página</b>', 'neto' => '<b>Neto</b>',);
            $fila = array(
                'pagina' => $pagina . '/' . $this->numpaginas,
                'neto' => $this->show_precio($this->factura->neto, $this->factura->coddivisa),
            );
            $opciones = array(
                'cols' => array(
                    'neto' => array('justification' => 'right'),
                ),
                'showLines' => 3,
                'shaded' => 2,
                'shadeCol2' => array(0.95, 0.95, 0.95),
                'lineCol' => array(0.3, 0.3, 0.3),
                'width' => 520
            );
            foreach($lineas_iva as $li)
            {
               $imp = $this->impuesto->get($li['codimpuesto']);
               if($imp)
               {
                  $titulo['iva'.$li['iva']] = '<b>'.$imp->descripcion.'</b>';
               }
               else
                  $titulo['iva'.$li['iva']] = '<b>'.FS_IVA.' '.$li['iva'].'%</b>';
               
               $fila['iva'.$li['iva']] = $this->show_precio($li['totaliva'], $this->factura->coddivisa);
               
               if($li['totalrecargo'] != 0)
               {
                  $fila['iva'.$li['iva']] .= "\nR.E. ".$li['recargo']."%: ".$this->show_precio($li['totalrecargo'], $this->factura->coddivisa);
               }
               
               $opciones['cols']['iva'.$li['iva']] = array('justification' => 'right');
            }
            
            if($this->factura->totalirpf != 0)
            {
               $titulo['irpf'] = '<b>'.FS_IRPF.' '.$this->factura->irpf.'%</b>';
               $fila['irpf'] = $this->show_precio($this->factura->totalirpf);
               $opciones['cols']['irpf'] = array('justification' => 'right');
            }
            
            $titulo['liquido'] = '<b>Total</b>';
            $fila['liquido'] = $this->show_precio($this->factura->total, $this->factura->coddivisa);
            $opciones['cols']['liquido'] = array('justification' => 'right');
            $pdf_doc->add_table_header($titulo);
            $pdf_doc->add_table_row($fila);
            $pdf_doc->save_table($opciones);
            
            /// pié de página para la factura
            if($this->empresa->pie_factura)
            {
               $pdf_doc->pdf->addText(10, 10, 8, $pdf_doc->center_text($pdf_doc->fix_html($this->empresa->pie_factura), 180) );
            }
            
            $pagina++;
         }
      }
      else
      {
         $pdf_doc->pdf->ezText('¡'.ucfirst(FS_FACTURA).' sin líneas!', 20);
      }
      
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
         {
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         }
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show(FS_FACTURA.'_'.$this->factura->codigo.'.pdf');
   }
   
   private function enviar_email($doc, $tipo = 'simple')
   {
      if( $this->empresa->can_send_mail() )
      {
         if( $_POST['email'] != $this->cliente->email AND isset($_POST['guardar']) )
         {
            $this->cliente->email = $_POST['email'];
            $this->cliente->save();
         }
         
         if($doc == 'factura')
         {
            $filename = 'factura_'.$this->factura->codigo.'.pdf';
            $this->generar_pdf_factura($tipo, $filename);
         }
         else
         {
            $filename = 'albaran_'.$this->albaran->codigo.'.pdf';
            $this->generar_pdf_albaran($filename);
         }
         
         if( file_exists('tmp/'.FS_TMP_NAME.'enviar/'.$filename) )
         {
            $mail = $this->empresa->new_mail();
            $mail->FromName = $this->user->get_agente_fullname();
            $mail->addReplyTo($_POST['de'], $mail->FromName);
            
            $mail->addAddress($_POST['email'], $this->cliente->razonsocial);
            if($_POST['email_copia'])
            {
               if( isset($_POST['cco']) )
               {
                  $mail->addBCC($_POST['email_copia'], $this->cliente->razonsocial);
               }
               else
               {
                  $mail->addCC($_POST['email_copia'], $this->cliente->razonsocial);
               }
            }
            
            if($doc == 'factura')
            {
               $mail->Subject = $this->empresa->nombre . ': Su factura '.$this->factura->codigo;
            }
            else
            {
               $mail->Subject = $this->empresa->nombre . ': Su '.FS_ALBARAN.' '.$this->albaran->codigo;
            }
            
            $mail->AltBody = $_POST['mensaje'];
            $mail->msgHTML( nl2br($_POST['mensaje']) );
            $mail->isHTML(TRUE);
            
            $mail->addAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            if( is_uploaded_file($_FILES['adjunto']['tmp_name']) )
            {
               $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
            }
            
            if( $this->empresa->mail_connect($mail) )
            {
               if( $mail->send() )
               {
                  $this->new_message('Mensaje enviado correctamente.');
                  
                  /// nos guardamos la fecha de envío
                  if($doc == 'factura')
                  {
                     $this->factura->femail = $this->today();
                     $this->factura->save();
                  }
                  else
                  {
                     $this->albaran->femail = $this->today();
                     $this->albaran->save();
                  }
                  
                  $this->empresa->save_mail($mail);
               }
               else
                  $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            }
            else
               $this->new_error_msg("Error al enviar el email: " . $mail->ErrorInfo);
            
            unlink('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
         }
         else
            $this->new_error_msg('Imposible generar el PDF.');
      }
   }
   
   private function get_lineas_iva($lineas)
   {
      $retorno = array();
      $lineasiva = array();
      
      foreach($lineas as $lin)
      {
         if( isset($lineasiva[$lin->codimpuesto]) )
         {
            if($lin->recargo > $lineasiva[$lin->codimpuesto]['recargo'])
            {
               $lineasiva[$lin->codimpuesto]['recargo'] = $lin->recargo;
            }
            
            $lineasiva[$lin->codimpuesto]['neto'] += $lin->pvptotal;
            $lineasiva[$lin->codimpuesto]['totaliva'] += ($lin->pvptotal*$lin->iva)/100;
            $lineasiva[$lin->codimpuesto]['totalrecargo'] += ($lin->pvptotal*$lin->recargo)/100;
            $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto']
                    + $lineasiva[$lin->codimpuesto]['totaliva'] + $lineasiva[$lin->codimpuesto]['totalrecargo'];
         }
         else
         {
            $lineasiva[$lin->codimpuesto] = array(
                'codimpuesto' => $lin->codimpuesto,
                'iva' => $lin->iva,
                'recargo' => $lin->recargo,
                'neto' => $lin->pvptotal,
                'totaliva' => ($lin->pvptotal*$lin->iva)/100,
                'totalrecargo' => ($lin->pvptotal*$lin->recargo)/100,
                'totallinea' => 0
            );
            $lineasiva[$lin->codimpuesto]['totallinea'] = $lineasiva[$lin->codimpuesto]['neto']
                    + $lineasiva[$lin->codimpuesto]['totaliva'] + $lineasiva[$lin->codimpuesto]['totalrecargo'];
         }
      }
      
      foreach($lineasiva as $lin)
      {
         $retorno[] = $lin;
      }
      
      return $retorno;
   }
   
   // Funciones añadidas
   private function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', '"', $newt);
      $newt = str_replace('&#39;', "'", $newt);
      return $newt;
   }
   public function ckeckEuro($cadena)
   {
      $mostrar = $this->show_precio($cadena, $this->empresa->coddivisa);
      $pos = strpos($mostrar, '€');
      if ($pos !== false)
      {
         if (FS_POS_DIVISA == 'right')
         {
            return number_format($cadena, FS_NF0, FS_NF1, FS_NF2) . '' . EEURO;
         }
         else
         {
            return EEURO . ' ' . number_format($cadena, FS_NF0, FS_NF1, FS_NF2);
         }
      }
      return $mostrar;
   }
   private function _genera_formapago() 
   {
	$texto_pago = array();
        $fp0 = new forma_pago();
        if( isset($_REQUEST['proforma']) OR isset($_REQUEST['proforma_uk']) OR isset($_REQUEST['pedido'])){
          $forma_pago = $fp0->get($this->pedido->codpago);
          if($forma_pago)
                    {
             $texto_pago[] = $forma_pago->descripcion;
                            if($forma_pago->domiciliado) {
                                            $cbc0 = new cuenta_banco_cliente ();
                                            $encontrada = FALSE;
                                            foreach ( $cbc0->all_from_cliente ( $this->pedido->codcliente ) as $cbc ) {
                                                    $tmp_textopago = "Domiciliado en: ";
                                                    if ($cbc->iban) {
                                                            $texto_pago[] = $tmp_textopago. $cbc->iban ( TRUE );
                                                    }

                                                    if ($cbc->swift) {
                                                            $texto_pago[] = "SWIFT/BIC: " . $cbc->swift;
                                                    }
                                                    $encontrada = TRUE;
                                                    break;
                                            }
                                            if (! $encontrada) {
                                                    $texto_pago[] = "Cliente sin cuenta bancaria asignada";
                                            }
                            } else if ($forma_pago->codcuenta) {
                                            $cb0 = new cuenta_banco ();
                                            $cuenta_banco = $cb0->get ( $forma_pago->codcuenta );
                                            if ($cuenta_banco) {
                                                    if ($cuenta_banco->iban) {
                                                            $texto_pago[] = "IBAN: " . $cuenta_banco->iban ( TRUE );
                                                    }

                                                    if ($cuenta_banco->swift) {
                                                            $texto_pago[] = "SWIFT o BIC: " . $cuenta_banco->swift;
                                                    }
                                            }
                            }


             $texto_pago[] = " ";
                    }
          }else {      
            $forma_pago = $fp0->get($this->albaran->codpago);
            if($forma_pago)
                      {
               $texto_pago[] = $forma_pago->descripcion;
                              if($forma_pago->domiciliado) {
                                              $cbc0 = new cuenta_banco_cliente ();
                                              $encontrada = FALSE;
                                              foreach ( $cbc0->all_from_cliente ( $this->albaran->codcliente ) as $cbc ) {
                                                      $tmp_textopago = "Domiciliado en: ";
                                                      if ($cbc->iban) {
                                                              $texto_pago[] = $tmp_textopago. $cbc->iban ( TRUE );
                                                      }

                                                      if ($cbc->swift) {
                                                              $texto_pago[] = "SWIFT/BIC: " . $cbc->swift;
                                                      }
                                                      $encontrada = TRUE;
                                                      break;
                                              }
                                              if (! $encontrada) {
                                                      $texto_pago[] = "Cliente sin cuenta bancaria asignada";
                                              }
                              } else if ($forma_pago->codcuenta) {
                                              $cb0 = new cuenta_banco ();
                                              $cuenta_banco = $cb0->get ( $forma_pago->codcuenta );
                                              if ($cuenta_banco) {
                                                      if ($cuenta_banco->iban) {
                                                              $texto_pago[] = "IBAN: " . $cuenta_banco->iban ( TRUE );
                                                      }

                                                      if ($cuenta_banco->swift) {
                                                              $texto_pago[] = "SWIFT o BIC: " . $cuenta_banco->swift;
                                                      }
                                              }
                              }


               $texto_pago[] = " ";
                      }
            }
      return $texto_pago;
   }
}
