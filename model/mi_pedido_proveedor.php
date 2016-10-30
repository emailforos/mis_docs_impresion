<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014-2015  Francesc Pineda Segarra  shawe.ewahs@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('albaran_proveedor.php');
require_model('proveedor.php');
require_model('linea_pedido_proveedor.php');
require_model('linea_iva_pedido_proveedor.php');
require_model('secuencia.php');


/**
 * Pedido de proveedor
 */
class mi_pedido_proveedor extends fs_model {

   public $idpedido;
   public $idalbaran;
   public $codigo;
   public $codserie;
   public $codejercicio;
   public $codproveedor;
   public $codagente;
   public $codpago;
   public $coddivisa;
   public $codalmacen;
   public $numero;
   public $numproveedor;
   public $nombre;
   public $cifnif;
   public $fecha;
   public $hora;
   public $neto;
   public $total;
   public $totaliva;
   public $totaleuros;
   public $irpf;
   public $totalirpf;
   public $tasaconv;
   public $recfinanciero;
   public $totalrecargo;
   public $observaciones;
   public $editable;

   public function __construct($p = FALSE)
   {
      parent::__construct('pedidosprov', 'plugins/presupuestos_y_pedidos/');
      if($p)
      {
         $this->idpedido = $this->intval($p['idpedido']);
         $this->idalbaran = $this->intval($p['idalbaran']);
         $this->codigo = $p['codigo'];
         $this->codagente = $p['codagente'];
         $this->codpago = $p['codpago'];
         $this->codserie = $p['codserie'];
         $this->codejercicio = $p['codejercicio'];
         $this->codproveedor = $p['codproveedor'];
         $this->coddivisa = $p['coddivisa'];
         $this->codalmacen = $p['codalmacen'];
         $this->numero = $p['numero'];
         $this->numproveedor = $p['numproveedor'];
         $this->nombre = $p['nombre'];
         $this->cifnif = $p['cifnif'];
         $this->fecha = Date('d-m-Y', strtotime($p['fecha']));

         $this->hora = Date('H:i:s', strtotime($p['fecha']));
         if (!is_null($p['hora']))
            $this->hora = $p['hora'];

         $this->neto = floatval($p['neto']);
         $this->total = floatval($p['total']);
         $this->totaliva = floatval($p['totaliva']);
         $this->totaleuros = floatval($p['totaleuros']);
         $this->irpf = floatval($p['irpf']);
         $this->totalirpf = floatval($p['totalirpf']);
         $this->tasaconv = floatval($p['tasaconv']);
         $this->recfinanciero = floatval($p['recfinanciero']);
         $this->totalrecargo = floatval($p['totalrecargo']);
         $this->observaciones = $p['observaciones'];
         $this->editable = $this->str2bool($p['editable']);
      }
      else
      {
         $this->idpedido = NULL;
         $this->idalbaran = NULL;
         $this->codigo = NULL;
         $this->codagente = NULL;
         $this->codpago = NULL;
         $this->codserie = NULL;
         $this->codejercicio = NULL;
         $this->codproveedor = NULL;
         $this->coddivisa = NULL;
         $this->codalmacen = NULL;
         $this->numero = NULL;
         $this->numproveedor = NULL;
         $this->nombre = NULL;
         $this->cifnif = NULL;
         $this->fecha = Date('d-m-Y');
         $this->hora = Date('H:i:s');
         $this->neto = 0;
         $this->total = 0;
         $this->totaliva = 0;
         $this->totaleuros = 0;
         $this->irpf = 0;
         $this->totalirpf = 0;
         $this->tasaconv = 1;
         $this->recfinanciero = 0;
         $this->totalrecargo = 0;
         $this->observaciones = NULL;
         $this->editable = TRUE;
      }
   }

   protected function install()
   {
      return '';
   }

   public function show_hora($s = TRUE)
   {
      if($s)
      {
         return Date('H:i:s', strtotime($this->hora));
      }
      else
         return Date('H:i', strtotime($this->hora));
   }

   public function observaciones_resume()
   {
      if ($this->observaciones == '')
      {
         return '-';
      }
      else if (strlen($this->observaciones) < 60)
      {
         return $this->observaciones;
      }
      else
         return substr($this->observaciones, 0, 50) . '...';
   }

   public function url()
   {
      if( is_null($this->idpedido) )
      {
         return 'index.php?page=compras_pedidos';
      }
      else
         return 'index.php?page=compras_pedido&id='.$this->idpedido;
   }

   public function albaran_url()
   {
      if( is_null($this->idalbaran) )
      {
         return 'index.php?page=compras_albaranes';
      }
      else
         return 'index.php?page=compras_albaran&id='.$this->idalbaran;
   }

   public function agente_url()
   {
      if( is_null($this->codagente) )
      {
         return "index.php?page=admin_agentes";
      }
      else
         return "index.php?page=admin_agente&cod=".$this->codagente;
   }

   public function proveedor_url()
   {
      if( is_null($this->codproveedor) )
      {
         return "index.php?page=compras_proveedores";
      }
      else
         return "index.php?page=compras_proveedor&cod=".$this->codproveedor;
   }

   public function get_lineas()
   {
      $linea = new linea_pedido_proveedor();
      return $linea->all_from_pedido($this->idpedido);
   }

   public function get($id)
   {
      $pedido = $this->db->select("SELECT * FROM ".$this->table_name." WHERE idpedido = ".$this->var2str($id).";");
      if($pedido)
      {
         return new pedido_proveedor($pedido[0]);
      }
      else
         return FALSE;
   }

   public function exists()
   {
      if( is_null($this->idpedido) )
      {
         return FALSE;
      }
      else
         return $this->db->select("SELECT * FROM ".$this->table_name." WHERE idpedido = ".$this->var2str($this->idpedido).";");
   }

   public function new_codigo()
   {
      $sec = new secuencia();
      $sec = $sec->get_by_params2($this->codejercicio, $this->codserie, 'npedidoprov');
      if($sec)
      {
         $this->numero = $sec->valorout;
         $sec->valorout++;
         $sec->save();
      }

      if(!$sec OR $this->numero <= 1)
      {
         $numero = $this->db->select("SELECT MAX(" . $this->db->sql_to_int('numero') . ") as num
            FROM " . $this->table_name . " WHERE codejercicio = " . $this->var2str($this->codejercicio) .
                 " AND codserie = " . $this->var2str($this->codserie) . ";");
         if($numero)
         {
            $this->numero = 1 + intval($numero[0]['num']);
         }
         else
            $this->numero = 1;

         if($sec)
         {
            $sec->valorout = 1 + $this->numero;
            $sec->save();
         }
      }

      if(FS_NEW_CODIGO == 'eneboo')
      {
         $this->codigo = $this->codejercicio.sprintf('%02s', $this->codserie).sprintf('%06s', $this->numero);
      }
      else
      {
         $this->codigo = strtoupper(substr(FS_PEDIDO, 0, 3)).$this->codejercicio.$this->codserie.$this->numero.'C';
      }
   }

   public function test()
   {
      $this->observaciones = $this->no_html($this->observaciones);
      $this->totaleuros = $this->total * $this->tasaconv;

      if ($this->floatcmp($this->total, $this->neto + $this->totaliva - $this->totalirpf + $this->totalrecargo, FS_NF0, TRUE))
      {
         return TRUE;
      }
      else
      {
         $this->new_error_msg("Error grave: El total está mal calculado. ¡Informa del error!");
         return FALSE;
      }
   }

   public function full_test($duplicados = TRUE)
   {
      $status = TRUE;

      /// comprobamos las líneas
      $neto = 0;
      $iva = 0;
      $irpf = 0;
      $recargo = 0;
      foreach ($this->get_lineas() as $l)
      {
         if (!$l->test())
            $status = FALSE;

         $neto += $l->pvptotal;
         $iva += $l->pvptotal * $l->iva / 100;
         $irpf += $l->pvptotal * $l->irpf / 100;
         $recargo += $l->pvptotal * $l->recargo / 100;
      }

      $neto = round($neto, FS_NF0);
      $iva = round($iva, FS_NF0);
      $irpf = round($irpf, FS_NF0);
      $recargo = round($recargo, FS_NF0);
      $total = $neto + $iva - $irpf + $recargo;

      if (!$this->floatcmp($this->neto, $neto, FS_NF0, TRUE))
      {
         $this->new_error_msg("Valor neto de " . FS_PEDIDO . " incorrecto. Valor correcto: " . $neto);
         $status = FALSE;
      }
      else if (!$this->floatcmp($this->totaliva, $iva, FS_NF0, TRUE))
      {
         $this->new_error_msg("Valor totaliva de " . FS_PEDIDO . " incorrecto. Valor correcto: " . $iva);
         $status = FALSE;
      }
      else if (!$this->floatcmp($this->totalirpf, $irpf, FS_NF0, TRUE))
      {
         $this->new_error_msg("Valor totalirpf de " . FS_PEDIDO . " incorrecto. Valor correcto: " . $irpf);
         $status = FALSE;
      }
      else if (!$this->floatcmp($this->totalrecargo, $recargo, FS_NF0, TRUE))
      {
         $this->new_error_msg("Valor totalrecargo de " . FS_PEDIDO . " incorrecto. Valor correcto: " . $recargo);
         $status = FALSE;
      }
      else if (!$this->floatcmp($this->total, $total, FS_NF0, TRUE))
      {
         $this->new_error_msg("Valor total de " . FS_PEDIDO . " incorrecto. Valor correcto: " . $total);
         $status = FALSE;
      }
      else if (!$this->floatcmp($this->totaleuros, $this->total * $this->tasaconv, FS_NF0, TRUE))
      {
         $this->new_error_msg("Valor totaleuros de ".FS_PEDIDO." incorrecto. Valor correcto: ".round($this->total*$this->tasaconv, FS_NF0));
         $status = FALSE;
      }
      
      if($this->idalbaran)
      {
         $alb0 = new albaran_proveedor();
         $albaran = $alb0->get($this->idalbaran);
         if (!$albaran)
         {
            $this->idalbaran = NULL;
            $this->save();
         }
      }

      return $status;
   }

   public function save()
   {
      if( $this->test() )
      {
         if( $this->exists() )
         {
            $sql = "UPDATE " . $this->table_name . " SET cifnif = " . $this->var2str($this->cifnif) . ",
               codagente = " . $this->var2str($this->codagente) . ", codalmacen = " . $this->var2str($this->codalmacen) . ",
               codproveedor = " . $this->var2str($this->codproveedor) . ",
               coddivisa = " . $this->var2str($this->coddivisa) . ", codejercicio = " . $this->var2str($this->codejercicio) . ",
               codigo = " . $this->var2str($this->codigo) . ", codpago = " . $this->var2str($this->codpago) . ",
               codserie = " . $this->var2str($this->codserie) . ",
               editable = " . $this->var2str($this->editable) . ", fecha = " . $this->var2str($this->fecha) . ", hora = " . $this->var2str($this->hora) . ",
               idalbaran = " . $this->var2str($this->idalbaran) . ",
               irpf = " . $this->var2str($this->irpf) . ", neto = " . $this->var2str($this->neto) . ",
               nombre = " . $this->var2str($this->nombre) . ", numero = " . $this->var2str($this->numero) . ",
               numproveedor = " . $this->var2str($this->numproveedor) . ", observaciones = " . $this->var2str($this->observaciones) . ", 
               recfinanciero = " . $this->var2str($this->recfinanciero) . ",
               tasaconv = " . $this->var2str($this->tasaconv) . ",
               total = " . $this->var2str($this->total) . ", totaleuros = " . $this->var2str($this->totaleuros) . ",
               totalirpf = " . $this->var2str($this->totalirpf) . ", totaliva = " . $this->var2str($this->totaliva) . ",
               totalrecargo = " . $this->var2str($this->totalrecargo) . " WHERE idpedido = " . $this->var2str($this->idpedido) . ";";
            
            return $this->db->exec($sql);
         }
         else
         {
            $this->new_codigo();
            $sql = "INSERT INTO ".$this->table_name." (cifnif,codagente,codalmacen,codproveedor,coddivisa,codejercicio,codigo,codpago,codserie,
               editable,fecha,hora,idalbaran,irpf,neto,nombre,numero,observaciones,recfinanciero,tasaconv,total,totaleuros,
               totalirpf,totaliva,totalrecargo,numproveedor) VALUES (" . $this->var2str($this->cifnif) . ",
               " . $this->var2str($this->codagente) . "," . $this->var2str($this->codalmacen) . ",
               " . $this->var2str($this->codproveedor) . "," . $this->var2str($this->coddivisa) . ",
               " . $this->var2str($this->codejercicio) . "," . $this->var2str($this->codigo) . ",
               " . $this->var2str($this->codpago) . "," . $this->var2str($this->codserie) . ",
               " . $this->var2str($this->editable) . "," . $this->var2str($this->fecha) . ",
               " . $this->var2str($this->hora) . "," . $this->var2str($this->idalbaran) . ",
               " . $this->var2str($this->irpf) . "," . $this->var2str($this->neto) . "," . $this->var2str($this->nombre) . ",
               " . $this->var2str($this->numero) . "," . $this->var2str($this->observaciones) . ",
               " . $this->var2str($this->recfinanciero) . ",
               " . $this->var2str($this->tasaconv) . "," . $this->var2str($this->total) . "," . $this->var2str($this->totaleuros) . ",
               " . $this->var2str($this->totalirpf) . "," . $this->var2str($this->totaliva) . "," . $this->var2str($this->totalrecargo) . ",
               " . $this->var2str($this->numproveedor) . ");";

            if ($this->db->exec($sql))
            {
               $this->idpedido = $this->db->lastval();
               return TRUE;
            }
            else
               return FALSE;
         }
      }
      else
         return FALSE;
   }

   public function delete()
   {
      if( $this->db->exec("DELETE FROM " . $this->table_name . " WHERE idpedido = " . $this->var2str($this->idpedido) . ";") )
      {
         if($this->idalbaran)
         {
            /**
             * Delegamos la eliminación en la clase correspondiente,
             * que tendrá que hacer más cosas.
             */
            $albaran = new albaran_proveedor();
            $alb0 = $albaran->get($this->idalbaran);
            if($alb0)
            {
               $alb0->delete();
            }
         }
         
         return TRUE;
      }
      else
         return FALSE;
   }

   public function all($offset = 0)
   {
      $pedilist = array();
      
      $pedidos = $this->db->select_limit("SELECT * FROM " . $this->table_name . " ORDER BY fecha DESC, codigo DESC", FS_ITEM_LIMIT, $offset);
      if ($pedidos)
      {
         foreach ($pedidos as $p)
            $pedilist[] = new pedido_proveedor($p);
      }
      
      return $pedilist;
   }

   public function all_ptealbaran($offset = 0, $order = 'DESC')
   {
      $pedilist = array();
      
      $pedidos = $this->db->select_limit("SELECT * FROM " . $this->table_name .
              " WHERE idalbaran IS NULL ORDER BY fecha " . $order . ", codigo " . $order, FS_ITEM_LIMIT, $offset);
      if ($pedidos)
      {
         foreach ($pedidos as $p)
            $pedilist[] = new pedido_proveedor($p);
      }
      
      return $pedilist;
   }
   
   public function all_from_proveedor($codproveedor, $offset = 0)
   {
      $pedilist = array();
      
      $pedidos = $this->db->select_limit("SELECT * FROM " . $this->table_name .
              " WHERE codproveedor = " . $this->var2str($codproveedor) .
              " ORDER BY fecha DESC, codigo DESC", FS_ITEM_LIMIT, $offset);
      if ($pedidos)
      {
         foreach ($pedidos as $p)
            $pedilist[] = new pedido_proveedor($p);
      }
      
      return $pedilist;
   }

   public function all_from_agente($codagente, $offset = 0)
   {
      $pedilist = array();
      
      $pedidos = $this->db->select_limit("SELECT * FROM " . $this->table_name .
              " WHERE codagente = " . $this->var2str($codagente) ." ORDER BY fecha DESC, codigo DESC", FS_ITEM_LIMIT, $offset);
      if ($pedidos)
      {
         foreach ($pedidos as $p)
            $pedilist[] = new pedido_proveedor($p);
      }
      
      return $pedilist;
   }

   public function all_desde($desde, $hasta)
   {
      $pedlist = array();
      
      $pedidos = $this->db->select("SELECT * FROM " . $this->table_name .
              " WHERE fecha >= " . $this->var2str($desde) . " AND fecha <= " . $this->var2str($hasta) ." ORDER BY codigo ASC;");
      if ($pedidos)
      {
         foreach ($pedidos as $p)
            $pedlist[] = new pedido_proveedor($p);
      }
      
      return $pedlist;
   }
   
   public function search($query, $offset = 0)
   {
      $pedilist = array();
      $query = strtolower($this->no_html($query));

      $consulta = "SELECT * FROM " . $this->table_name . " WHERE ";
      if (is_numeric($query))
      {
         $consulta .= "codigo LIKE '%" . $query . "%' OR numproveedor LIKE '%" . $query . "%' OR observaciones LIKE '%" . $query . "%'
            OR total BETWEEN '" . ($query - .01) . "' AND '" . ($query + .01) . "'";
      }
      else if (preg_match('/^([0-9]{1,2})-([0-9]{1,2})-([0-9]{4})$/i', $query))
      {
         /// es una fecha
         $consulta .= "fecha = " . $this->var2str($query) . " OR observaciones LIKE '%" . $query . "%'";
      }
      else
      {
         $consulta .= "lower(codigo) LIKE '%" . $query . "%' OR lower(numproveedor) LIKE '%" . $query . "%' "
                 . "OR lower(observaciones) LIKE '%" . str_replace(' ', '%', $query) . "%'";
      }
      $consulta .= " ORDER BY fecha DESC, codigo DESC";

      $pedidos = $this->db->select_limit($consulta, FS_ITEM_LIMIT, $offset);
      if ($pedidos)
      {
         foreach ($pedidos as $p)
            $pedilist[] = new pedido_proveedor($p);
      }
      
      return $pedilist;
   }
   
   public function cron_job()
   {
      $this->db->exec("UPDATE ".$this->table_name." SET idalbaran = NULL WHERE idalbaran NOT IN (SELECT idalbaran FROM albaranesprov);");
   }
   public function get_lineas_iva()
   {
      $linea_iva = new linea_iva_pedido_proveedor();
      $lineasi = $linea_iva->all_from_pedido($this->idpedido);
      /// si no hay lineas de IVA las generamos
      if( !$lineasi )
      {
         $lineas = $this->get_lineas();
         if($lineas)
         {
            foreach($lineas as $l)
            {
               $i = 0;
               $encontrada = FALSE;
               while($i < count($lineasi))
               {
                  if($l->codimpuesto == $lineasi[$i]->codimpuesto)
                  {
                     $encontrada = TRUE;
                     $lineasi[$i]->neto += $l->pvptotal;
                     $lineasi[$i]->totaliva += ($l->pvptotal*$l->iva)/100;
                     $lineasi[$i]->totalrecargo += ($l->pvptotal*$l->recargo)/100;
                  }
                  $i++;
               }
               if( !$encontrada )
               {
                  $lineasi[$i] = new linea_iva_factura_proveedor();
                  $lineasi[$i]->idfactura = $this->idfactura;
                  $lineasi[$i]->codimpuesto = $l->codimpuesto;
                  $lineasi[$i]->iva = $l->iva;
                  $lineasi[$i]->recargo = $l->recargo;
                  $lineasi[$i]->neto = $l->pvptotal;
                  $lineasi[$i]->totaliva = ($l->pvptotal*$l->iva)/100;
                  $lineasi[$i]->totalrecargo = ($l->pvptotal*$l->recargo)/100;
               }
            }
            
            /// redondeamos y guardamos
            if( count($lineasi) == 1 )
            {
               $lineasi[0]->neto = round($lineasi[0]->neto, FS_NF0);
               $lineasi[0]->totaliva = round($lineasi[0]->totaliva, FS_NF0);
               $lineasi[0]->totalrecargo = round($lineasi[0]->totalrecargo, FS_NF0);
               $lineasi[0]->totallinea = $lineasi[0]->neto + $lineasi[0]->totaliva + $lineasi[0]->totalrecargo;
               $lineasi[0]->save();
            }
            else
            {
               /*
                * Como el neto y el iva se redondean en la factura, al dividirlo
                * en líneas de iva podemos encontrarnos con un descuadre que
                * hay que calcular y solucionar.
                */
               $t_neto = 0;
               $t_iva = 0;
               foreach($lineasi as $li)
               {
                  $li->neto = bround($li->neto, FS_NF0);
                  $li->totaliva = bround($li->totaliva, FS_NF0);
                  $li->totallinea = $li->neto + $li->totaliva + $li->totalrecargo;
                  
                  $t_neto += $li->neto;
                  $t_iva += $li->totaliva;
               }
               
               if( !$this->floatcmp($this->neto, $t_neto) )
               {
                  /*
                   * Sumamos o restamos un céntimo a los netos más altos
                   * hasta que desaparezca el descuadre
                   */
                  $diferencia = round( ($this->neto-$t_neto) * 100 );
                  usort($lineasi, function($a, $b) {
                     if($a->totallinea == $b->totallinea)
                        return 0;
                     else
                        return ($a->totallinea < $b->totallinea) ? 1 : -1;
                  });
                  
                  foreach($lineasi as $i => $value)
                  {
                     if($diferencia > 0)
                     {
                        $lineasi[$i]->neto += .01;
                        $diferencia--;
                     }
                     else if($diferencia < 0)
                     {
                        $lineasi[$i]->neto -= .01;
                        $diferencia++;
                     }
                     else
                        break;
                  }
               }
               
               if( !$this->floatcmp($this->totaliva, $t_iva) )
               {
                  /*
                   * Sumamos o restamos un céntimo a los netos más altos
                   * hasta que desaparezca el descuadre
                   */
                  $diferencia = round( ($this->totaliva-$t_iva) * 100 );
                  usort($lineasi, function($a, $b) {
                     if($a->totallinea == $b->totallinea)
                        return 0;
                     else
                        return ($a->totallinea < $b->totallinea) ? 1 : -1;
                  });
                  
                  foreach($lineasi as $i => $value)
                  {
                     if($diferencia > 0)
                     {
                        $lineasi[$i]->totaliva += .01;
                        $diferencia--;
                     }
                     else if($diferencia < 0)
                     {
                        $lineasi[$i]->totaliva -= .01;
                        $diferencia++;
                     }
                     else
                        break;
                  }
               }
               
               foreach($lineasi as $i => $value)
               {
                  $lineasi[$i]->totallinea = $value->neto + $value->totaliva + $value->totalrecargo;
                  $lineasi[$i]->save();
               }
            }
         }
      }
      return $lineasi;
   }
}
