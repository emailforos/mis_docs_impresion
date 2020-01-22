<?php

/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2016  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * Personalización de /presupuestos_y_pedidos/imprimir_presu_pedi.php
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

require_once 'plugins/mis_docs_impresion/fpdf17/fs_fp_fpdf.php';

if(!defined('FPDF_FONTPATH'))
{
   define('FPDF_FONTPATH', 'plugins/mi_factura_detallada/fpdf17/font/');
}

require_once 'extras/phpmailer/class.phpmailer.php';
require_once 'extras/phpmailer/class.smtp.php';
require_model('articulo_proveedor.php');
require_model('cliente.php');
require_model('impuesto.php');
require_model('pedido_cliente.php');
require_model('pedido_proveedor.php');
require_model('presupuesto_cliente.php');
require_model('proveedor.php');

// Añadidos
require_model('forma_pago.php');
require_model('pais.php');
require_model('cuenta_banco.php');
require_model('cuenta_banco_cliente.php');
require_model('agencia_transporte.php');
require_model('idioma_fac_det.php');


/**
 * Esta clase agrupa los procedimientos de imprimir/enviar presupuestos y pedidos.
 */
class mis_docs_impresion extends fs_controller
{
   public $articulo_proveedor;
   public $cliente;
   public $impresion;
   public $impuesto;
   public $pedido;
   public $presupuesto;
   public $proveedor;
   public $idioma;   
   
   private $numpaginas;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'imprimir', 'ventas', FALSE, FALSE);
   }
   
   protected function private_core()
   {
      $this->articulo_proveedor = new articulo_proveedor();
      $this->cliente = FALSE;
      $this->impuesto = new impuesto();
      $this->pedido = FALSE;
      $this->presupuesto = FALSE;
      $this->proveedor = FALSE;
      
      /// obtenemos los datos de configuración de impresión
      $this->impresion = array(
          'print_ref' => '1',
          'print_dto' => '1',
          'print_alb' => '0',
          'print_formapago' => '1'
      );
      /// cargamos el idioma
      $idi0 = new idioma_fac_det();
      if(isset($_REQUEST['codidioma']))
      {
         $this->idioma = $idi0->get($_REQUEST['codidioma']);
      }
      else
      {
         foreach($idi0->all() as $idi)
         {
            $this->idioma = $idi;
            break;
         }
      }
      $fsvar = new fs_var();
      $this->impresion = $fsvar->array_get($this->impresion, FALSE);
      
      if( isset($_REQUEST['pedido_p']) AND isset($_REQUEST['id']) )
      {
         $ped = new pedido_proveedor();
         $this->pedido = $ped->get($_REQUEST['id']);
         if($this->pedido)
         {
            $proveedor = new proveedor();
            $this->proveedor = $proveedor->get($this->pedido->codproveedor);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email_proveedor();
         }
         else
            $this->generar_pdf_pedido_proveedor();
      }
      else if( isset($_REQUEST['pedido_p_uk']) AND isset($_REQUEST['id']) )
      {
         $ped = new pedido_proveedor();
         $this->pedido = $ped->get($_REQUEST['id']);
         if($this->pedido)
         {
            $proveedor = new proveedor();
            $this->proveedor = $proveedor->get($this->pedido->codproveedor);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email_proveedor();
         }
         else
            $this->generar_pdf_pedido_proveedor_uk();
      }
      else if( isset($_REQUEST['pedido']) AND isset($_REQUEST['id']) )
      {
         $ped = new pedido_cliente();
         $this->pedido = $ped->get($_REQUEST['id']);
         if($this->pedido)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->pedido->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('pedido');
         }
         else
            $this->generar_pdf_pedido();
      }
      else if( isset($_REQUEST['presupuesto']) AND isset($_REQUEST['id']) )
      {
         $pres = new presupuesto_cliente();
         $this->presupuesto = $pres->get($_REQUEST['id']);
         if($this->presupuesto)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->presupuesto->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('presupuesto');
         }
         else
            $this->generar_pdf_presupuesto();
      }else if( isset($_REQUEST['presupuesto_uk']) AND isset($_REQUEST['id']) )
      {
         $pres = new presupuesto_cliente();
         $this->presupuesto = $pres->get($_REQUEST['id']);
         if($this->presupuesto)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->presupuesto->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('presupuesto_uk');
         }
         else
            $this->generar_pdf_presupuesto_uk();
      }
      else if( isset($_REQUEST['proforma']) AND isset($_REQUEST['id']) )
      {
         $pres = new pedido_cliente();
         $this->pedido = $pres->get($_REQUEST['id']);
         if($this->pedido)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->pedido->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('proforma');
         }
         else
            $this->generar_pdf_proforma();
      }
      else if( isset($_REQUEST['proforma_uk']) AND isset($_REQUEST['id']) )
      {
         $pres = new pedido_cliente();
         $this->pedido = $pres->get($_REQUEST['id']);
         if($this->pedido)
         {
            $cliente = new cliente();
            $this->cliente = $cliente->get($this->pedido->codcliente);
         }
         
         if( isset($_POST['email']) )
         {
            $this->enviar_email('proforma_uk');
         }
         else
            $this->generar_pdf_proforma_uk();
      }
      
      
      $this->share_extensions();
   }
   
   private function share_extensions()
   {
       foreach($this->idioma->all() as $idi)
      {
         //var_dump ($idi); exit();
         $fsext = new fs_extension();
         $fsext->name = 'imprimir_pedido_proveedor_' . $idi->codidioma;
         $fsext->from = __CLASS__;
         $fsext->to = 'compras_pedido';
         $fsext->type = 'pdf';
         $fsext->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Imprimir PEDIDO ' . $idi->codidioma;
         $fsext->params = '&pedido_p=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext->save();
         }
         else
         {
            $fsext->delete();
         }

         $fsext2 = new fs_extension();
         $fsext2->name = 'email_pedido_proveedor_' . $idi->codidioma;
         $fsext2->from = __CLASS__;
         $fsext2->to = 'compras_pedido';
         $fsext2->type = 'email';
         $fsext2->text = 'Enviar el PEDIDO ' . $idi->codidioma;
         $fsext2->params = '&pedido_p=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext2->save();
         }
         else
         {
            $fsext2->delete();
         }
         $fsext3 = new fs_extension();
         $fsext3->name = 'imprimir_pedido_' . $idi->codidioma;
         $fsext3->from = __CLASS__;
         $fsext3->to = 'ventas_pedido';
         $fsext3->type = 'pdf';
         $fsext3->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Imprimir CONFIRMACION ' . $idi->codidioma;
         $fsext3->params = '&pedido=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext3->save();
         }
         else
         {
            $fsext3->delete();
         }
         
         $fsext4 = new fs_extension();
         $fsext4->name = 'email_pedido_' . $idi->codidioma;
         $fsext4->from = __CLASS__;
         $fsext4->to = 'ventas_pedido';
         $fsext4->type = 'email';
         $fsext4->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Enviar email CONFIRMACION ' . $idi->codidioma;
         $fsext4->params = '&pedido=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext4->save();
         }
         else
         {
            $fsext4->delete();
         }
         
         $fsext5 = new fs_extension();
         $fsext5->name = 'imprimir_presupuesto_' . $idi->codidioma;
         $fsext5->from = __CLASS__;
         $fsext5->to = 'ventas_presupuesto';
         $fsext5->type = 'pdf';
         $fsext5->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Imprimir PRESUPUESTO ' . $idi->codidioma;
         $fsext5->params = '&presupuesto=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext5->save();
         }
         else
         {
            $fsext5->delete();
         }         
         
         $fsext6 = new fs_extension();
         $fsext6->name = 'email_presupuesto_' . $idi->codidioma;
         $fsext6->from = __CLASS__;
         $fsext6->to = 'ventas_presupuesto';
         $fsext6->type = 'email';
         $fsext6->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Enviar PRESUPUESTO por email ' . $idi->codidioma;
         $fsext6->params = '&presupuesto=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext6->save();
         }
         else
         {
            $fsext6->delete();
         }
         
         $fsext7 = new fs_extension();
         $fsext7->name = 'imprimir_proforma_' . $idi->codidioma;
         $fsext7->from = __CLASS__;
         $fsext7->to = 'ventas_pedido';
         $fsext7->type = 'pdf';
         $fsext7->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Imprimir PROFORMA ' . $idi->codidioma;
         $fsext7->params = '&proforma=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext7->save();
         }
         else
         {
            $fsext7->delete();
         }
         
         $fsext8 = new fs_extension();
         $fsext8->name = 'email_proforma_' . $idi->codidioma;
         $fsext8->from = __CLASS__;
         $fsext8->to = 'ventas_pedido';
         $fsext8->type = 'email';
         $fsext8->text = '<span class="glyphicon glyphicon-check"></span>&nbsp; Enviar email PROFORMA ' . $idi->codidioma;
         $fsext8->params = '&proforma=TRUE&codidioma=' . $idi->codidioma;
         
         if($idi->activo)
         {
            $fsext8->save();
         }
         else
         {
            $fsext8->delete();
         }         
      }

      /// eliminamos las antiguas extensiones
      $fsext = new fs_extension();
      $fsext->name = 'imprimir_pedido_proveedor';
      $fsext->from = __CLASS__;
      $fsext->delete();

      $fsext2 = new fs_extension();
      $fsext2->name = 'email_pedido_proveedor';
      $fsext2->from = __CLASS__;
      $fsext2->delete();
      
      $fsext3 = new fs_extension();
      $fsext3->name = 'imprimir_pedido';
      $fsext3->from = __CLASS__;
      $fsext3->delete();
      
      $fsext4 = new fs_extension();
      $fsext4->name = 'email_pedido';
      $fsext4->from = __CLASS__;
      $fsext4->delete();
      
      $fsext5 = new fs_extension();
      $fsext5->name = 'imprimir_presupuesto';
      $fsext5->from = __CLASS__;
      $fsext5->delete();
      
      $fsext6 = new fs_extension();
      $fsext6->name = 'email_presupuesto';
      $fsext6->from = __CLASS__;
      $fsext6->delete();
            
      $fsext7 = new fs_extension();
      $fsext7->name = 'imprimir_proforma';
      $fsext7->from = __CLASS__;
      $fsext7->delete();
            
      $fsext8 = new fs_extension();
      $fsext8->name = 'email_proforma';
      $fsext8->from = __CLASS__;
      $fsext8->delete();
            
      $fsext9 = new fs_extension();
      $fsext9->name = 'imprimir_proforma_uk';
      $fsext9->from = __CLASS__;
      $fsext9->delete();
      
      
          
   }
   
   /**
    * Añade las líneas al documento pdf.
    * @param fs_pdf $pdf_doc
    * @param type $lineas
    * @param type $linea_actual
    * @param type $lppag
    * @param type $documento
    */
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
          'cantidad' => '<b>Cant.</b>',
          'descripcion' => '<b>Ref. + Descripción</b>',
          'cantidad2' => '<b>Cant.</b>',
          'pvp' => '<b>PVP</b>',
      );
      
      if( get_class_name($lineas[$linea_actual]) == 'linea_pedido_proveedor' )
      {
         unset($table_header['cantidad2']);
         $table_header['descripcion'] = '<b>Ref. Prov. + Descripción</b>';
      }
      else
      {
         unset($table_header['cantidad']);
      }
      
      if( isset($_GET['noval']) )
      {
         unset($table_header['pvp']);
      }
      
      if( $this->impresion['print_dto'] AND !isset($_GET['noval']) )
      {
         $table_header['dto'] = '<b>Dto.</b>';
      }
      
      if( $multi_iva AND !isset($_GET['noval']) )
      {
         $table_header['iva'] = '<b>'.FS_IVA.'</b>';
      }
      
      if( $multi_re AND !isset($_GET['noval']) )
      {
         $table_header['re'] = '<b>R.E.</b>';
      }
      
      if( $multi_irpf AND !isset($_GET['noval']) )
      {
         $table_header['irpf'] = '<b>'.FS_IRPF.'</b>';
      }
      
      if( !isset($_GET['noval']) )
      {
         $table_header['importe'] = '<b>Importe</b>';
      }
      
      $pdf_doc->add_table_header($table_header);
      
      for($i = $linea_actual; (($linea_actual < ($lppag + $i)) AND ($linea_actual < count($lineas)));)
      {
         $descripcion = $pdf_doc->fix_html($lineas[$linea_actual]->descripcion);
         if( !is_null($lineas[$linea_actual]->referencia) )
         {
            if( get_class_name($lineas[$linea_actual]) == 'linea_pedido_proveedor' )
            {
               $descripcion = '<b>'.$this->get_referencia_proveedor($lineas[$linea_actual]->referencia, $documento->codproveedor)
                       .'</b> '.$descripcion;
            }
            else
            {
               $descripcion = '<b>'.$lineas[$linea_actual]->referencia.'</b> '.$descripcion;
            }
         }
         
         $fila = array(
             'cantidad' => $this->show_numero($lineas[$linea_actual]->cantidad, $dec_cantidad),
             'cantidad2' => $this->show_numero($lineas[$linea_actual]->cantidad, $dec_cantidad),
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
         
         if( get_class_name($lineas[$linea_actual]) != 'linea_pedido_proveedor' )
         {
            if( !$lineas[$linea_actual]->mostrar_cantidad )
            {
               $fila['cantidad'] = '';
               $fila['cantidad2'] = '';
            }
            
            if( !$lineas[$linea_actual]->mostrar_precio )
            {
               $fila['pvp'] = '';
               $fila['dto'] = '';
               $fila['iva'] = '';
               $fila['re'] = '';
               $fila['irpf'] = '';
               $fila['importe'] = '';
            }
         }
         
         $pdf_doc->add_table_row($fila);
         $linea_actual++;
      }
      
      $pdf_doc->save_table(
              array(
                  'fontSize' => 8,
                  'cols' => array(
                      'cantidad' => array('justification' => 'right'),
                      'cantidad2' => array('justification' => 'right'),
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
      
      if( $linea_actual == count($lineas) )
      {
         if($documento->observaciones != '')
         {
            $pdf_doc->pdf->ezText("\n".$pdf_doc->fix_html($documento->observaciones), 9);
         }
      }
   }
   private function generar_pdf_proforma ($archivo = FALSE)
   {
      if( !$archivo )
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      ob_end_clean();
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $pdf_doc->idioma = $this->idioma;
      $lineas = $this->pedido->get_lineas();

      $pdf_doc->SetTitle(ucfirst($this->idioma->proforma).': '. $this->pedido->codigo);
      $pdf_doc->SetSubject(ucfirst($this->idioma->cliente).': '. $this->pedido->nombrecliente);
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
      $pdf_doc->fde_telefono = ucfirst($this->idioma->telefono) . ': '. $this->empresa->telefono;
      $pdf_doc->fde_fax = ucfirst($this->idioma->fax) . ': ' . $this->empresa->fax;
      $pdf_doc->fde_email = $this->empresa->email;
      $pdf_doc->fde_web = $this->empresa->web;
      $pdf_doc->fde_piefactura = $this->empresa->pie_factura;
      
      /// Insertamos el Logo y Marca de Agua
      if( file_exists(FS_MYDOCS.'images/logo.png') OR file_exists(FS_MYDOCS.'images/logo.jpg') )
      {
         $pdf_doc->fdf_verlogotipo = '1'; // 1/0 --> Mostrar Logotipo
         $pdf_doc->fdf_Xlogotipo = '18'; // Valor X para Logotipo
         $pdf_doc->fdf_Ylogotipo = '10'; // Valor Y para Logotipo
         $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
         $pdf_doc->fdf_Xmarcaagua = '29'; // Valor X para Marca de Agua
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
      $pdf_doc->fdf_tipodocumento = 'Proforma'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->pedido->codigo;
      /*Nosotros no usamos NUMERO2 aqui . " " . $this->factura->numero2*/

      // Fecha, Codigo cliente y observaciones del presupuesto
      $pdf_doc->fdf_fecha = $this->pedido->fecha;
      $pdf_doc->fdf_codcliente = $this->pedido->codcliente;
      $pdf_doc->fdf_observaciones = iconv("UTF-8", "CP1252", $this->idioma->fix_html($this->pedido->observaciones));
      $pdf_doc->fdf_numero2 = $this->pedido->numero2;
    
     // Datos del Cliente
      $pdf_doc->fdf_nombrecliente = $this->idioma->fix_html($this->pedido->nombrecliente);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->pedido->cifnif;
      $pdf_doc->fdf_direccion = $this->idioma->fix_html($this->pedido->direccion);
      $pdf_doc->fdf_codpostal = $this->pedido->codpostal;
      $pdf_doc->fdf_ciudad = $this->pedido->ciudad;
      $pdf_doc->fdf_provincia = $this->pedido->provincia;
      $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
      $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
      $pdf_doc->fdc_fax = $this->cliente->fax;
      $pdf_doc->fdc_email = $this->cliente->email;
      
      // Fecha salida pedido
      $pdf_doc->fdf_salida = $this->pedido->fechasalida;
      
      $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';
           
      // Forma de Pago del presupuesto
      $formapago = $this->_genera_formapago();
      $pdf_doc->fdf_epago = $formapago;
      
      // Divisa del presupuesto
      $divisa = new divisa();
      $edivisa = $divisa->get($this->pedido->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }

      // Pais de la proforma
      $pais = new pais();
      $epais = $pais->get($this->pedido->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
      
      // Agencia transporte 
      $agencia = new agencia_transporte();
      $eagencia = $agencia->get($this->pedido->envio_codtrans);
      if ($eagencia) {
         $pdf_doc->fdf_agencia = $eagencia->nombre;
      } else {
          $pdf_doc->fdf_agencia = "SUS MEDIOS";
      }
      
      /*// Cabecera Titulos Columnas
      $pdf_doc->Setdatoscab(array('ART.','DESCRIPCI'.chr(211).'N', 'CANT', 'PRECIO', 'DTO', 'NETO', 'IMPORTE'));
      $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
      $pdf_doc->SetAligns(array('L','L', 'R', 'R', 'R', 'R', 'R'));
      $pdf_doc->SetColors(array('0|0|0','0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));*/
      
      // Cabecera Titulos Columnas
      if($this->impresion['print_dto'])
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 75, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      else
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      
      /// Definimos todos los datos del PIE de la proforma
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
            $filaiva[1][6] = $this->pedido->irpf.' %';
            $etemp = round($this->pedido->totalirpf,2);
            $totaliva = $totaliva - $etemp;
            $filaiva[1][7] = ($etemp) ? $this->ckeckEuro($etemp) : '';
         }
         
         $pdf_doc->fdf_lineasiva = $filaiva;
      }
      
      // Total factura numerico
      $etemp = round($this->pedido->total,2);
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($etemp);

      // Total factura numeros a texto
      $pdf_doc->fdf_textotal = $this->pedido->total;


      /// Agregamos la pagina inicial de la factura
      $pdf_doc->AddPage();
      
      // Lineas del pedido
      //$lineas = $this->factura->get_lineas();
      
      if ($lineas) {
         $neto = 0;
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            if ($art && $art->partnumber!="") {
               $observa = "\n" . "Ref. " . utf8_decode( $this->idioma->fix_html($art->partnumber) );
            } else {
               // $observa = null; // No mostrar mensaje de error
               $observa = "\n";
            }
            if($lineas[$i]->referencia){
                $referencia = $lineas[$i]->referencia;
            }else {
                $referencia = '';
            }
            if( !$lineas[$i]->mostrar_cantidad ){
                $cantidad = '';
            } else {
                    $cantidad = $lineas[$i]->cantidad;
            }
            if( !$lineas[$i]->mostrar_precio )
            {
               $pvpunitario = '';
               $dtopor = '';
               $pneto = '';
               $pvptotal = '';
            } else {
               $pvpunitario = $this->ckeckEuro($lineas[$i]->pvpunitario);
               $dtopor = $this->show_numero($lineas[$i]->dtopor, 1) /*. "%"*/;
               $pneto = $this->ckeckEuro(($lineas[$i]->pvpunitario)*((100-$lineas[$i]->dtopor)/100));
               $pvptotal = $this->ckeckEuro($lineas[$i]->pvptotal);
            }
              
            $lafila = array(
                    '0' => utf8_decode($referencia),
                    '1' => utf8_decode($lineas[$i]->descripcion) . $observa,
                    '2' => utf8_decode($cantidad),
                    '3' => $pvpunitario,
                    '4' => utf8_decode($dtopor),
                    '5' => $pneto,
                    '6' => $pvptotal, // Importe con Descuentos aplicados
                    //'5' => $this->ckeckEuro($lineas[$i]->total_iva())
                    );
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
         $pdf_doc->Output('Proforma '. $this->pedido->codigo . ' ' . utf8_decode($this->idioma->fix_html($this->pedido->nombrecliente)) . '.pdf','I');
      }
   }
      
   private function generar_pdf_presupuesto($archivo = FALSE)
   {
      if( !$archivo )
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      ob_end_clean();
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $pdf_doc->idioma = $this->idioma;
      $lineas = $this->presupuesto->get_lineas();

      $pdf_doc->SetTitle(ucfirst($this->idioma->presupuesto).': '. $this->presupuesto->codigo)/*. " " . $this->factura->numero2)*/;
      $pdf_doc->SetSubject(ucfirst($this->idioma->cliente).': '. utf8_decode($this->presupuesto->nombrecliente));
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
         $pdf_doc->fdf_Xlogotipo = '18'; // Valor X para Logotipo
         $pdf_doc->fdf_Ylogotipo = '10'; // Valor Y para Logotipo
         $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
         $pdf_doc->fdf_Xmarcaagua = '29'; // Valor X para Marca de Agua
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
      $pdf_doc->fdf_tipodocumento = 'Oferta'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->presupuesto->codigo; 
      $pdf_doc->fdf_numero2 = $this->presupuesto->numero2;    
      /*Nosotros no usamos NUMERO2 aqui . " " . $this->factura->numero2*/

      // Fecha, Codigo cliente y observaciones del presupuesto
      $pdf_doc->fdf_fecha = $this->presupuesto->fecha;
      $pdf_doc->fdf_codcliente = $this->presupuesto->codcliente;
      $pdf_doc->fdf_observaciones = iconv("UTF-8", "CP1252", $this->idioma->fix_html($this->presupuesto->observaciones));
    
     // Datos del Cliente
      $pdf_doc->fdf_nombrecliente = $this->idioma->fix_html($this->presupuesto->nombrecliente);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->presupuesto->cifnif;
      $pdf_doc->fdf_direccion = $this->idioma->fix_html($this->presupuesto->direccion);
      $pdf_doc->fdf_codpostal = $this->presupuesto->codpostal;
      $pdf_doc->fdf_ciudad = $this->presupuesto->ciudad;
      $pdf_doc->fdf_provincia = $this->presupuesto->provincia;
      $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
      $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
      $pdf_doc->fdc_fax = $this->cliente->fax;
      $pdf_doc->fdc_email = $this->cliente->email;
      
      $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';

      // Forma de Pago del presupuesto
      $formapago = $this->_genera_formapago();
      $pdf_doc->fdf_epago = $formapago;
      
      // Divisa del presupuesto
      $divisa = new divisa();
      $edivisa = $divisa->get($this->presupuesto->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }

      // Pais del presupuesto
      $pais = new pais();
      $epais = $pais->get($this->presupuesto->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
      
      // Agencia transporte 
      $agencia = new agencia_transporte();
      $eagencia = $agencia->get($this->presupuesto->envio_codtrans);
      if ($eagencia) {
         $pdf_doc->fdf_agencia = $eagencia->nombre;
      } else {
          $pdf_doc->fdf_agencia = "SUS MEDIOS";
      }
      
      // Fecha salida pedido
      $pdf_doc->fdf_salida = '';
      
      // Cabecera Titulos Columnas
      /*$pdf_doc->Setdatoscab(array('ART.','DESCRIPCI'.chr(211).'N', 'CANT', 'PRECIO', 'DTO', 'NETO', 'IMPORTE'));
      $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
      $pdf_doc->SetAligns(array('L','L', 'R', 'R', 'R', 'R', 'R'));
      $pdf_doc->SetColors(array('0|0|0','0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));*/
      // Cabecera Titulos Columnas
      if($this->impresion['print_dto'])
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 75, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      else
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      
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
            $filaiva[1][6] = $this->presupuesto->irpf.' %';
            $etemp = round($this->presupuesto->totalirpf,2);
            $totaliva = $totaliva - $etemp;
            $filaiva[1][7] = ($etemp) ? $this->ckeckEuro($etemp) : '';
         }
         
         $pdf_doc->fdf_lineasiva = $filaiva;
      }
      
      // Total factura numerico
      $etemp = round($this->presupuesto->total,2);
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($etemp);

      // Total factura numeros a texto
      $pdf_doc->fdf_textotal = $this->presupuesto->total;


      /// Agregamos la pagina inicial de la factura
      $pdf_doc->AddPage();
      
      // Lineas del presupuesto
      //$lineas = $this->factura->get_lineas();
      
      if ($lineas) {
         $neto = 0;
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            if ($art && $art->partnumber!="") {
               $observa = "\n" . "Ref. " . utf8_decode(ucfirst($this->idioma->fix_html($art->partnumber)));
            } else {
               // $observa = null; // No mostrar mensaje de error
               $observa = "\n";
            }
            if($lineas[$i]->referencia){
                $referencia = $lineas[$i]->referencia;
            }else {
                $referencia = '';
            }
            if( !$lineas[$i]->mostrar_cantidad ){
                $cantidad = '';
            } else {
                    $cantidad = $lineas[$i]->cantidad;
            }
            if( !$lineas[$i]->mostrar_precio )
            {
               $pvpunitario = '';
               $dtopor = '';
               $pneto = '';
               $pvptotal = '';
            } else {
               $pvpunitario = $this->ckeckEuro($lineas[$i]->pvpunitario);
               $dtopor = $this->show_numero($lineas[$i]->dtopor, 2) /*. "%"*/;
               $pneto = $this->ckeckEuro(($lineas[$i]->pvpunitario)*((100-$lineas[$i]->dtopor)/100));
               $pvptotal = $this->ckeckEuro($lineas[$i]->pvptotal);
            }
              
            $lafila = array(
                    '0' => utf8_decode($referencia),
                    '1' => utf8_decode($lineas[$i]->descripcion) . $observa,
                    '2' => utf8_decode($cantidad),
                    '3' => $pvpunitario,
                    '4' => utf8_decode($dtopor),
                    '5' => $pneto,
                    '6' => $pvptotal, // Importe con Descuentos aplicados
                    //'5' => $this->ckeckEuro($lineas[$i]->total_iva())
                    );
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
         $pdf_doc->Output('Oferta ' . $this->presupuesto->codigo . ' ' . utf8_decode($this->idioma->fix_html($this->presupuesto->nombrecliente)) . '.pdf','I');
      }
   }
  
   private function generar_pdf_pedido_proveedor($archivo = FALSE)
   {
      if( !$archivo )
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      // Parte mía//
      ///// INICIO - Pedido compras DETALLADO
      /// Creamos el PDF y escribimos sus metadatos
      ob_end_clean();
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $pdf_doc->idioma = $this->idioma;
      $lineas = $this->pedido->get_lineas();

      $pdf_doc->SetTitle(ucfirst($this->idioma->compra).': '. $this->pedido->codigo)/*. " " . $this->factura->numero2)*/;
      $pdf_doc->SetSubject('Proveedor/Supplier: '. utf8_decode($this->pedido->nombre));
      $pdf_doc->SetAuthor($this->empresa->nombre);
      $pdf_doc->SetCreator('FacturaSctipts V_' . $this->version());

      $pdf_doc->Open();
      $pdf_doc->AliasNbPages();
      $pdf_doc->SetAutoPageBreak(true, 40);

      // Definimos el color de relleno (gris, rojo, verde, azul)
      $pdf_doc->SetColorRelleno('gris');
      
      /// Definimos todos los datos de la cabecera de la factura
      /// Datos de la empresa
      $pdf_doc->fde_nombre = $this->empresa->nombre;
      $pdf_doc->fde_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fde_cifnif = $this->empresa->cifnif;
      $pdf_doc->fde_direccion = $this->empresa->direccion;
      $pdf_doc->fde_codpostal = $this->empresa->codpostal;
      $pdf_doc->fde_ciudad = $this->empresa->ciudad;
      $pdf_doc->fde_provincia = $this->empresa->provincia;
      $pdf_doc->fde_telefono = ucfirst($this->idioma->telefono) . ': '. $this->empresa->telefono;
      $pdf_doc->fde_fax = ucfirst($this->idioma->fax) . ': ' . $this->empresa->fax;
      $pdf_doc->fde_email = $this->empresa->email;
      $pdf_doc->fde_web = $this->empresa->web;
      $pdf_doc->fde_piefactura = $this->empresa->pie_factura;

           /// Insertamos el Logo y Marca de Agua
      if( file_exists(FS_MYDOCS.'images/logo.png') OR file_exists(FS_MYDOCS.'images/logo.jpg') )
      {
         $pdf_doc->fdf_verlogotipo = '1'; // 1/0 --> Mostrar Logotipo
         $pdf_doc->fdf_Xlogotipo = '18'; // Valor X para Logotipo
         $pdf_doc->fdf_Ylogotipo = '10'; // Valor Y para Logotipo
         $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
         $pdf_doc->fdf_Xmarcaagua = '29'; // Valor X para Marca de Agua
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
      $pdf_doc->fdf_tipodocumento = 'Compra'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->pedido->codigo; 
      $pdf_doc->fdf_numero2 = $this->pedido->numproveedor; 

      /*No sé para que lo usan . " " . $this->factura->numero2*/

      // Fecha, Codigo proveedor y observaciones del pedido
      $pdf_doc->fdf_fecha = $this->pedido->fecha;
      $pdf_doc->fdf_codcliente = $this->pedido->codproveedor;
      $pdf_doc->fdf_observaciones = utf8_decode( $this->idioma->fix_html($this->pedido->observaciones) );
    
     // Datos del Proveedor
      $pdf_doc->fdf_nombrecliente = $this->idioma->fix_html($this->pedido->nombre);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->pedido->cifnif;
     // Coger datos proveedor
      foreach($this->proveedor->get_direcciones() as $dir)
      {
         if($dir->direccionppal)
         {
            $pdf_doc->fdf_direccion = $dir->direccion;
            $pdf_doc->fdf_codpostal = $dir->codpostal;
            $pdf_doc->fdf_ciudad = $dir->ciudad;
            $pdf_doc->fdf_provincia = $dir->provincia;
            break;
         }
      }
      $prov = new proveedor();
      $eprov = $prov->get($this->pedido->codproveedor);
      if ($eprov) {
         $pdf_doc->fdc_telefono1 = $eprov->telefono1;
         $pdf_doc->fdc_telefono2 = $eprov->telefono2;
         $pdf_doc->fdc_fax = $eprov->fax;
         $pdf_doc->fdc_email = $eprov->email;
         $pdf_doc->fdc_web = $eprov->web;
      }
          
      $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';
      
      // Forma de Pago del pedido
      $formapago = $this->_genera_formapago();
      $pdf_doc->fdf_epago = $formapago;

      // Divisa de la Factura
      $divisa = new divisa();
      $edivisa = $divisa->get($this->pedido->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }
      
      // Pais del presupuesto
      $pais = new pais();
      $epais = $pais->get($dir->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
            
      // Cabecera Titulos Columnas
      if($this->impresion['print_dto'])
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 75, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      else
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      
      /// Definimos todos los datos del PIE del pedido
      /// Lineas de IVA
      $lineas = $this->pedido->get_lineas();
      $lineas_iva = $this->get_lineas_iva ($lineas);
      /* original
      if($lineas)
      {
         ...
         $pdf_doc->pdf->ezText('¡'.ucfirst(FS_PEDIDO).' sin líneas!', 20);
      }
      */
      
      
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
            $filaiva[1][6] = $this->pedido->irpf.' %';
            $etemp = round($this->pedido->totalirpf,2);
            $totaliva = $totaliva - $etemp;
            $filaiva[1][7] = ($etemp) ? $this->ckeckEuro($etemp) : '';
         }
         
         $pdf_doc->fdf_lineasiva = $filaiva;
      }
      // Total pedido numerico
      $etemp = round($this->pedido->total,2);
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($etemp);

      // Total pedido numeros a texto
      $pdf_doc->fdf_textotal = $this->pedido->total;

      /// Agregamos la pagina inicial de la pedido
      $pdf_doc->AddPage();
      
      // Lineas del pedido.
      $lineas = $this->pedido->get_lineas();

      if ($lineas) {
         $neto = 0;
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            if ($art) {
               $observa = "\n" . utf8_decode( $this->idioma->fix_html($art->observaciones) );
            } else {
               // $observa = null; // No mostrar mensaje de error
               $observa = "\n";
            }
            if($lineas[$i]->referencia){
                $referencia = $lineas[$i]->referencia;
            }else {
                $referencia = '';
            }
            /*if( !$lineas[$i]->mostrar_cantidad ){
                $cantidad = '';
            } else {*/
                    $cantidad = $lineas[$i]->cantidad;
            /*}*/
            /*if( !$lineas[$i]->mostrar_precio )
            {
               $pvpunitario = '';
               $dtopor = '';
               $pneto = '';
               $pvptotal = '';
            } else {*/
               $pvpunitario = $this->ckeckEuro($lineas[$i]->pvpunitario);
               $dtopor = $this->show_numero($lineas[$i]->dtopor,1) /*. "%"*/;
               $pneto = $this->ckeckEuro(($lineas[$i]->pvpunitario)*((100-$lineas[$i]->dtopor)/100));
               $pvptotal = $this->ckeckEuro($lineas[$i]->pvptotal);
            /*}*/
              
            $lafila = array(
                    '0' => utf8_decode($referencia),
                    '1' => utf8_decode($lineas[$i]->descripcion) . $observa,
                    '2' => utf8_decode($cantidad),
                    '3' => $pvpunitario,
                    '4' => utf8_decode($dtopor),
                    '5' => $pneto,
                    '6' => $pvptotal, // Importe con Descuentos aplicados
                    //'5' => $this->ckeckEuro($lineas[$i]->total_iva())
                    );
            $pdf_doc->Row($lafila, '1'); // Row(array, Descripcion del Articulo -- ultimo valor a imprimir)
         }
         $pdf_doc->piepagina = true;
      }

      // Damos salida al archivo PDF
      /* Version original
      if($archivo)
      {
         if( !file_exists('tmp/'.FS_TMP_NAME.'enviar') )
         {
            mkdir('tmp/'.FS_TMP_NAME.'enviar');
         }
         
         $pdf_doc->save('tmp/'.FS_TMP_NAME.'enviar/'.$archivo);
      }
      else
         $pdf_doc->show(FS_PEDIDO.'_compra_'.$this->pedido->codigo.'.pdf');  
       */
      if ($archivo)
      {
         if (!file_exists('tmp/' . FS_TMP_NAME . 'enviar'))
         {
            mkdir('tmp/' . FS_TMP_NAME . 'enviar');
         }
         
         $pdf_doc->Output('tmp/' . FS_TMP_NAME . 'enviar/' . $archivo, 'F');
      }
      else
      {
         //$pdf_doc->Output();
         $pdf_doc->Output('PO '. $this->pedido->codigo . '.pdf','I');
      }
      
   }
        
   private function get_referencia_proveedor($ref, $codproveedor)
   {
      $artprov = $this->articulo_proveedor->get_by($ref, $codproveedor);
      if($artprov)
      {
         return $artprov->refproveedor;
      }
      else
         return $ref;
   }
   
   private function generar_pdf_pedido($archivo = FALSE)
   {
      if( !$archivo )
      {
         /// desactivamos la plantilla HTML
         $this->template = FALSE;
      }
      
      ob_end_clean();
      $pdf_doc = new PDF_MC_Table('P', 'mm', 'A4');
      define('EEURO', chr(128));
      $pdf_doc->idioma = $this->idioma;
      $lineas = $this->pedido->get_lineas();

      $pdf_doc->SetTitle(ucfirst($this->idioma->pedido).': '. $this->pedido->codigo);
      $pdf_doc->SetSubject(ucfirst($this->idioma->cliente).': '. $this->pedido->nombrecliente);
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
      $pdf_doc->fde_telefono = ucfirst($this->idioma->telefono) . ': '. $this->empresa->telefono;
      $pdf_doc->fde_fax = ucfirst($this->idioma->fax) . ': ' . $this->empresa->fax;
      $pdf_doc->fde_email = $this->empresa->email;
      $pdf_doc->fde_web = $this->empresa->web;
      $pdf_doc->fde_piefactura = $this->empresa->pie_factura;
      
      /// Insertamos el Logo y Marca de Agua
      if( file_exists(FS_MYDOCS.'images/logo.png') OR file_exists(FS_MYDOCS.'images/logo.jpg') )
      {
         $pdf_doc->fdf_verlogotipo = '1'; // 1/0 --> Mostrar Logotipo
         $pdf_doc->fdf_Xlogotipo = '18'; // Valor X para Logotipo
         $pdf_doc->fdf_Ylogotipo = '10'; // Valor Y para Logotipo
         $pdf_doc->fdf_vermarcaagua = '1'; // 1/0 --> Mostrar Marca de Agua
         $pdf_doc->fdf_Xmarcaagua = '29'; // Valor X para Marca de Agua
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
      $pdf_doc->fdf_tipodocumento = 'Pedido'; // (FACTURA, FACTURA PROFORMA, ¿ALBARAN, PRESUPUESTO?...)
      $pdf_doc->fdf_codigo = $this->pedido->codigo;
      /*Nosotros no usamos NUMERO2 aqui . " " . $this->factura->numero2*/

      // Fecha, Codigo cliente y observaciones del presupuesto
      $pdf_doc->fdf_fecha = $this->pedido->fecha;
      $pdf_doc->fdf_codcliente = $this->pedido->codcliente;
      $pdf_doc->fdf_observaciones = iconv("UTF-8", "CP1252", $this->idioma->fix_html($this->pedido->observaciones));
      $pdf_doc->fdf_numero2 = $this->pedido->numero2;

    
     // Datos del Cliente
      $pdf_doc->fdf_nombrecliente = $this->idioma->fix_html($this->pedido->nombrecliente);
      $pdf_doc->fdf_FS_CIFNIF = FS_CIFNIF;
      $pdf_doc->fdf_cifnif = $this->pedido->cifnif;
      $pdf_doc->fdf_direccion = $this->idioma->fix_html($this->pedido->direccion);
      $pdf_doc->fdf_codpostal = $this->pedido->codpostal;
      $pdf_doc->fdf_ciudad = $this->pedido->ciudad;
      $pdf_doc->fdf_provincia = $this->pedido->provincia;
      $pdf_doc->fdc_telefono1 = $this->cliente->telefono1;
      $pdf_doc->fdc_telefono2 = $this->cliente->telefono2;
      $pdf_doc->fdc_fax = $this->cliente->fax;
      $pdf_doc->fdc_email = $this->cliente->email;
      
      // Fecha salida pedido
      $pdf_doc->fdf_salida = $this->pedido->fechasalida;
      
      $pdf_doc->fdf_epago = $pdf_doc->fdf_divisa = $pdf_doc->fdf_pais = '';
           
      // Forma de Pago del presupuesto
      $formapago = $this->_genera_formapago();
      $pdf_doc->fdf_epago = $formapago;
      
      // Divisa del presupuesto
      $divisa = new divisa();
      $edivisa = $divisa->get($this->pedido->coddivisa);
      if ($edivisa) {
         $pdf_doc->fdf_divisa = $edivisa->descripcion;
      }

      // Pais del presupuesto
      $pais = new pais();
      $epais = $pais->get($this->pedido->codpais);
      if ($epais) {
         $pdf_doc->fdf_pais = $epais->nombre;
      }
      
      // Agencia transporte 
      $agencia = new agencia_transporte();
      $eagencia = $agencia->get($this->pedido->envio_codtrans);
      if ($eagencia) {
         $pdf_doc->fdf_agencia = $eagencia->nombre;
      } else {
          $pdf_doc->fdf_agencia = "SUS MEDIOS";
      }
      
      /*// Cabecera Titulos Columnas
      $pdf_doc->Setdatoscab(array('ART.','DESCRIPCI'.chr(211).'N', 'CANT', 'PRECIO', 'DTO', 'NETO', 'IMPORTE'));
      $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
      $pdf_doc->SetAligns(array('L','L', 'R', 'R', 'R', 'R', 'R'));
      $pdf_doc->SetColors(array('0|0|0','0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));*/
      
      // Cabecera Titulos Columnas
      if($this->impresion['print_dto'])
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 75, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      else
      {
         $pdf_doc->Setdatoscab(
                 array(
                     utf8_decode( mb_strtoupper($this->idioma->articulo) ),
                     utf8_decode( mb_strtoupper($this->idioma->descripcion) ),
                     utf8_decode( mb_strtoupper($this->idioma->cant) ),
                     utf8_decode( mb_strtoupper($this->idioma->precio) ),
                     utf8_decode( mb_strtoupper($this->idioma->dto) ),
                     utf8_decode( mb_strtoupper($this->idioma->neto) ),
                     'SUBTOTAL',
                     //utf8_decode( mb_strtoupper($this->idioma->iva) ),
                     //utf8_decode( mb_strtoupper($this->idioma->importe) ),
                 )
         );
         $pdf_doc->SetWidths(array(25, 83, 10, 20, 10, 20, 22));
         $pdf_doc->SetAligns(array('L', 'L', 'R', 'R', 'R', 'R', 'R'));
         $pdf_doc->SetColors(array('0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0', '0|0|0'));
      }
      
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
            $filaiva[1][6] = $this->pedido->irpf.' %';
            $etemp = round($this->pedido->totalirpf,2);
            $totaliva = $totaliva - $etemp;
            $filaiva[1][7] = ($etemp) ? $this->ckeckEuro($etemp) : '';
         }
         
         $pdf_doc->fdf_lineasiva = $filaiva;
      }
      
      // Total factura numerico
      $etemp = round($this->pedido->total,2);
      $pdf_doc->fdf_numtotal = $this->ckeckEuro($etemp);

      // Total factura numeros a texto
      $pdf_doc->fdf_textotal = $this->pedido->total;


      /// Agregamos la pagina inicial de la factura
      $pdf_doc->AddPage();
      
      // Lineas del pedido
      //$lineas = $this->factura->get_lineas();
      
      if ($lineas) {
         $neto = 0;
         for ($i = 0; $i < count($lineas); $i++) {
            $neto += $lineas[$i]->pvptotal;
            $pdf_doc->neto = $this->ckeckEuro($neto);

            $articulo = new articulo();
            $art = $articulo->get($lineas[$i]->referencia);
            if ($art && $art->partnumber!="") {
               $observa = "\n" . "Art. " . utf8_decode( $this->idioma->fix_html($art->partnumber) ); 
            } else {
               // $observa = null; // No mostrar mensaje de error
               $observa = "\n";
            }
            if($lineas[$i]->referencia){
                $referencia = $lineas[$i]->referencia;
            }else {
                $referencia = '';
            }
            if( !$lineas[$i]->mostrar_cantidad ){
                $cantidad = '';
            } else {
                    $cantidad = $lineas[$i]->cantidad;
            }
            if( !$lineas[$i]->mostrar_precio )
            {
               $pvpunitario = '';
               $dtopor = '';
               $pneto = '';
               $pvptotal = '';
            } else {
               $pvpunitario = $this->ckeckEuro($lineas[$i]->pvpunitario);
               $dtopor = $this->show_numero($lineas[$i]->dtopor, 1) . "%";
               $pneto = $this->ckeckEuro(($lineas[$i]->pvpunitario)*((100-$lineas[$i]->dtopor)/100));
               $pvptotal = $this->ckeckEuro($lineas[$i]->pvptotal);
            }
              
            $lafila = array(
                    '0' => utf8_decode($referencia),
                    '1' => utf8_decode($lineas[$i]->descripcion) . $observa,
                    '2' => utf8_decode($cantidad),
                    '3' => $pvpunitario,
                    '4' => utf8_decode($dtopor),
                    '5' => $pneto,
                    '6' => $pvptotal, // Importe con Descuentos aplicados
                    //'5' => $this->ckeckEuro($lineas[$i]->total_iva())
                    );
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
         $pdf_doc->Output('Confirmacion '. $this->pedido->codigo . ' ' . utf8_decode($this->idioma->fix_html($this->pedido->nombrecliente)) . '.pdf','I');
      }
   }
   
   private function enviar_email_proveedor()
   {
      if( $this->empresa->can_send_mail() )
      {
         if( $_POST['email'] != $this->proveedor->email AND isset($_POST['guardar']) )
         {
            $this->proveedor->email = $_POST['email'];
            $this->proveedor->save();
         }
         
         $filename = 'pedido_'.$this->pedido->codigo.'.pdf';
         $this->generar_pdf_pedido_proveedor($filename);
         
         if( file_exists('tmp/'.FS_TMP_NAME.'enviar/'.$filename) )
         {
            $mail = $this->empresa->new_mail();
            $mail->FromName = $this->user->get_agente_fullname();
            $mail->addReplyTo($_POST['de'], $mail->FromName);
            
            $mail->addAddress($_POST['email'], $this->proveedor->razonsocial);
            if($_POST['email_copia'])
            {
               if( isset($_POST['cco']) )
               {
                  $mail->addBCC($_POST['email_copia'], $this->proveedor->razonsocial);
               }
               else
               {
                  $mail->addCC($_POST['email_copia'], $this->proveedor->razonsocial);
               }
            }
            
            $mail->Subject = $this->empresa->nombre . ': Nuestro/Our '.FS_PEDIDO.' '.$this->pedido->codigo;
            $mail->AltBody = $_POST['mensaje'];
            $mail->msgHTML( nl2br($_POST['mensaje']) );
            $mail->isHTML(TRUE);
            
            $mail->addAttachment('tmp/'.FS_TMP_NAME.'enviar/'.$filename);
            if( is_uploaded_file($_FILES['adjunto']['tmp_name']) )
            {
               $mail->addAttachment($_FILES['adjunto']['tmp_name'], $_FILES['adjunto']['name']);
            }
            
            if( $mail->smtpConnect($this->empresa->smtp_options()) )
            {
               if( $mail->send() )
               {
                  $this->new_message('Mensaje enviado correctamente.');
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
   
   private function enviar_email($doc)
   {
      if( $this->empresa->can_send_mail() )
      {         
         if($doc == 'presupuesto')
         {
            $filename = 'presupuesto_'.$this->presupuesto->codigo.'.pdf';
            $this->generar_pdf_presupuesto($filename);
            $razonsocial = $this->presupuesto->nombrecliente;
         }
         else if($doc == 'presupuesto_uk')
         {
            $filename = 'quotation_'.$this->presupuesto->codigo.'.pdf';
            $this->generar_pdf_presupuesto_uk($filename);
            $razonsocial = $this->presupuesto->nombrecliente;
         }
         else if($doc == 'proforma')
         {
            $filename = 'proforma_'.$this->pedido->codigo.'.pdf';
            $this->generar_pdf_proforma($filename);
            $razonsocial = $this->pedido->nombrecliente;
         }
         else if($doc == 'proforma_uk')
         {
            $filename = 'proforma_'.$this->pedido->codigo.'.pdf';
            $this->generar_pdf_proforma_uk($filename);
            $razonsocial = $this->pedido->nombrecliente;
         }           
         else 
         {
            $filename = 'pedido_'.$this->pedido->codigo.'.pdf';
            $this->generar_pdf_pedido($filename);
            $razonsocial = $this->pedido->nombrecliente;
         }
         
         if( $_POST['email'] != $this->cliente->email AND isset($_POST['guardar']) )
         {
            $this->cliente->email = $_POST['email'];
            $this->cliente->save();
         }
         
         if( file_exists('tmp/'.FS_TMP_NAME.'enviar/'.$filename) )
         {
            $mail = $this->empresa->new_mail();
            $mail->FromName = $this->user->get_agente_fullname();
            
            if($_POST['de'] != $mail->From)
            {
               $mail->addReplyTo($_POST['de'], $mail->FromName);
            }
            
            $mail->addAddress($_POST['email'], $razonsocial);
            if($_POST['email_copia'])
            {
               if( isset($_POST['cco']) )
               {
                  $mail->addBCC($_POST['email_copia'], $razonsocial);
               }
               else
               {
                  $mail->addCC($_POST['email_copia'], $razonsocial);
               }
            }
            
            if($doc == 'presupuesto')
            {
               $mail->Subject = $this->empresa->nombre . ': Oferta '.$this->presupuesto->codigo;
            }
            if($doc == 'presupuesto_uk')
            {
               $mail->Subject = $this->empresa->nombre . ': Quotation '.$this->presupuesto->codigo;
            }
            if ($doc == 'proforma'){  
                if ($this->pedido->numero2 != NULL){
                    $mail->Subject = $this->empresa->nombre . ': Factura proforma ' . $this->pedido->codigo . ' de su pedido ' . $this->pedido->numero2;
                } else {
                $mail->Subject = $this->empresa->nombre . ': Factura proforma ' . $this->pedido->codigo;
                }
            }
            if ($doc == 'proforma_uk'){  
                if ($this->pedido->numero2 != NULL){
                    $mail->Subject = $this->empresa->nombre . ': Proforma invoice ' . $this->pedido->codigo . ' of your order ' . $this->pedido->numero2;
                } else {
                $mail->Subject = $this->empresa->nombre . ': Proforma invoice ' . $this->pedido->codigo;
                }
            }
            if ($doc == 'pedido'){  
                if ($this->pedido->numero2 != NULL){
                    $mail->Subject = $this->empresa->nombre . ': Confirmación pedido ' . $this->pedido->codigo . ' de su pedido ' . $this->pedido->numero2;
                } else {
                $mail->Subject = $this->empresa->nombre . ': Confirmación pedido '.$this->pedido->codigo;
                }
            }
            if ($doc == 'pedido_uk'){  
                if ($this->pedido->numero2 != NULL){
                    $mail->Subject = $this->empresa->nombre . ': Order confirmation ' . $this->pedido->codigo . ' of your order ' . $this->pedido->numero2;
                } else {
                $mail->Subject = $this->empresa->nombre . ': Order confirmation '.$this->pedido->codigo;
                }
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
                  
                  /// nos guardamos la fecha del envío
                  if($doc == 'presupuesto')
                  {
                     $this->presupuesto->femail = $this->today();
                     $this->presupuesto->save();
                  }
                  else
                  {
                     $this->pedido->femail = $this->today();
                     $this->pedido->save();
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
   /*private function fix_html($txt)
   {
      $newt = str_replace('&lt;', '<', $txt);
      $newt = str_replace('&gt;', '>', $newt);
      $newt = str_replace('&quot;', '"', $newt);
      $newt = str_replace('&#39;', "'", $newt);
      return $newt;
   }*/
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
        if( isset($_REQUEST['proforma']) OR isset($_REQUEST['proforma_uk']) OR isset($_REQUEST['pedido']) OR isset($_REQUEST['pedido_p']) OR isset($_REQUEST['pedido_p_uk'])){
          $forma_pago = $fp0->get($this->pedido->codpago);
          if($forma_pago)
                    {
             $texto_pago[] = $forma_pago->descripcion;
                            if($forma_pago->domiciliado) {
                                            $cbc0 = new cuenta_banco_cliente ();
                                            $encontrada = FALSE;
                                            foreach ( $cbc0->all_from_cliente ( $this->pedido->codcliente ) as $cbc ) {
                                                    $tmp_textopago = "Domiciliado en: ";
                                                    if ($cbc->descripcion){
                                                        $texto_pago[] = $cbc->descripcion;
                                                    }
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
                                                    if ($cuenta_banco->descripcion){
                                                        $texto_pago[] = $cuenta_banco->descripcion;
                                                    }
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
            $forma_pago = $fp0->get($this->presupuesto->codpago);
            if($forma_pago)
                      {
               $texto_pago[] = $forma_pago->descripcion;
                              if($forma_pago->domiciliado) {
                                              $cbc0 = new cuenta_banco_cliente ();
                                              $encontrada = FALSE;
                                              foreach ( $cbc0->all_from_cliente ( $this->presupuesto->codcliente ) as $cbc ) {
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
   
   public function is_html($txt)
   {
      if( stripos($txt, '<html') === FALSE )
      {
         return FALSE;
      }
      else
      {
         return TRUE;
      }
   }
}
