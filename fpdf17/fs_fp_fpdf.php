<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Valentín González    valengon@hotmail.com 
 * Copyright (C) 2014-2015  Carlos Garcia Gomez  neorazorx@gmail.com
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

require_once 'plugins/mi_factura_detallada/fpdf17/fpdf.php';

class PDF_MC_Table extends FPDF {
    
    var $datoscab;
    var $widths;
    var $aligns;
    var $colores;
    var $extgstates = array();
    var $angle=0;
    var $lineaactual = 0;
    var $piepagina = false;	

    function Setdatoscab($v)
    {
        //Set the array
        $this->datoscab=$v;
    }	

    function SetWidths($w)
    {
        //Set the array
        $this->widths=$w;
    }

    function SetAligns($a)
    {
        //Set the array
        $this->aligns=$a;
    }

    function SetColors($a)
    {
        for($i=0;$i<count($a);$i++)
        {
            $datos = explode('|',$a[$i]);
            $this->colores[$i][0] = $datos[0];
            $this->colores[$i][1] = $datos[1];
            $this->colores[$i][2] = $datos[2];
        }
    }

    function SetColorRelleno($a)
    {	
        $this->SetFillColor(254,254,254); // Por defecto Gris
        if ($a == 'rojo') { $this->SetFillColor(253, 120, 120); }
        if ($a == 'verde') { $this->SetFillColor(120, 253, 165); }		
        if ($a == 'azul') { $this->SetFillColor(120, 158, 253); }
    }

    //Cabecera de pagina
    function Header()
    {
        // Datos de la empresa
        $this->AddFont('LiberationSans','','LiberationSans-Regular.php');
        $this->AddFont('LiberationSans','B','LiberationSans-Bold.php');
        $this->AddFont('LiberationSans','I','LiberationSans-Italic.php');


        $direccion = $this->fde_FS_CIFNIF . ": ES-" . utf8_decode($this->fde_cifnif) . "\n" . $this->fde_direccion;
        if($this->fde_codpostal && $this->fde_ciudad)
        {
            $direccion .= "\n" . $this->fde_codpostal . ' - ' . $this->fde_ciudad;
        } else {
            if($this->fde_codpostal) { $direccion .= "\n" . $this->fde_codpostal; }
            if($this->fde_ciudad) { $direccion .= "\n" . $this->fde_ciudad; }		
        }
        if($this->fde_provincia) { $direccion .= ' (' . $this->fde_provincia . ')'; }
        if($this->fde_telefono) { $direccion .= "\n" . $this->fde_telefono; }
        if($this->fde_fax) { $direccion .= "\n" . $this->fde_fax; }
        $this->addSociete(utf8_decode($this->fde_nombre), utf8_decode($direccion), utf8_decode($this->fde_email), utf8_decode($this->fde_web));

        //Logotipo
        if($this->fdf_verlogotipo == '1')
        {
           if( file_exists(FS_MYDOCS.'images/logo.png') )
           {
              $this->Image(FS_MYDOCS.'images/logo.png', $this->fdf_Xlogotipo, $this->fdf_Ylogotipo, 75);
           }
           else if( file_exists(FS_MYDOCS.'images/logo.jpg') )
           {
              $this->Image(FS_MYDOCS.'images/logo.jpg', $this->fdf_Xlogotipo, $this->fdf_Ylogotipo, 75);
           }

           $this->Ln(0);
        }

        //Marca de agua
        if ($this->fdf_vermarcaagua == '1')
        {
            // set alpha to semi-transparency
            $this->SetAlpha(0.05);
            // draw png image
            $this->Image(FS_MYDOCS.'images/logo.png',$this->fdf_Xmarcaagua,$this->fdf_Ymarcaagua,160);
            // restore full opacity
            $this->SetAlpha(1);			
            $this->Ln(0);
        }		

        // Tipo de Documento y Numero
        $this->fact_dev($this->fdf_tipodocumento, $this->fdf_codigo);
        if ($this->fdf_numero2){
            $this->addPedidocliente ($this->fdf_numero2);
        }

        // Fecha factura y Codigo Cliente
        $this->addDate($this->fdf_fecha);
	//$this->addVencimiento($this->fdf_vencimiento);	
        $this->addClient($this->fdf_codcliente);
        $this->addPageNumber($this->PageNo().'/{nb}');

        // Datos del Cliente fiscales
        $cliente  = $this->fdf_nombrecliente . "\n";
        if ($this->fdf_cifnif==NULL){
            $cliente .= "\n";   
        } else {
            $cliente .= $this->fdf_FS_CIFNIF . ": ";
            $cliente .= $this->fdf_cifnif . "\n";
        }
        $cliente .= $this->fdf_direccion . "\n";
        $cliente .= $this->fdf_codpostal . " - ";
        $cliente .= $this->fdf_ciudad . " (".$this->fdf_provincia.")\n";
        $cliente .= $this->fdf_pais . "\n";
        $cliente .= ucfirst( $this->idioma->fix_html($this->idioma->telefono)).': ' . $this->fdc_telefono1;
        if($this->fdc_telefono2) { $cliente .= " - " . $this->fdc_telefono2 . "\n"; } else { $cliente .= "\n"; }
        if($this->fdc_fax) { $cliente .= ucfirst($this->idioma->fix_html($this->idioma->fax)).': ' . $this->fdc_fax . "\n"; }
        if($this->fdc_email) { $cliente .= ucfirst($this->idioma->fix_html($this->idioma->email)).': '. $this->fdc_email . "\n"; }	
	    $this->addClientAdresse(utf8_decode($cliente));	

        // Datos cliente envío
        if ($this->fdf_nombrecliente_env){
            $cliente_env .= $this->fdf_nombrecliente_env . "\n";        
            $cliente_env .= $this->fdf_direccion_env . "\n";
            $cliente_env .= $this->fdf_codpostal_env . " - ";
            $cliente_env .= $this->fdf_ciudad_env . " (".$this->fdf_provincia_env.")\n";
            $cliente_env .= $this->fdf_pais_env . "\n";
            $this->addClientEntrega(utf8_decode($cliente_env)); 
        }       
        	

        // Forma de Pago del presupuesto / pedido
        $this->addPago($this->fdf_epago);

        if ($this->fdf_tipodocumento == 'Compra'){
            $this->fdf_salida='';
        }
        else 
        {        
            // Fecha salida pedido
            if ($this->fdf_salida){
                $this->addSalida($this->fdf_salida);
            }  
        }
        if ($this->fdf_tipodocumento == 'Compra'){
            $this->fdf_agencia='DDP (Incoterms) - Portes pagados';
            $this->addAgencia(utf8_decode($this->fdf_agencia));

        }
        else 
        {        
            // Agencia del envío
            $this->addAgencia(utf8_decode($this->fdf_agencia));
        }   
        
        // Divisa de la Factura
        //$this->addDivisa(utf8_decode($this->fdf_divisa));

        // Pais de la Factura
        //$this->addPais(utf8_decode($this->fdf_pais));

        // Pie de la Factura
        //$this->SetFont('LiberationSans','',5);
        //$this->RotatedText(6, 210, utf8_decode($this->fde_piefactura), 90);
        $this->SetFont('LiberationSans','',7);
        $this->SetY(-8);
        $this->SetLineWidth(0.1);		
        $this->SetTextColor(0);
        $this->Cell(0,4, utf8_decode($this->fde_piefactura), 0, 0, "C");

        // Cabecera Titulos Columnas
        $this->SetXY(18, 95);
        $this->SetFont( "LiberationSans", "B", 9);
        for($i=0;$i<count($this->datoscab);$i++) 
        {
            $this->Cell($this->widths[$i],5,$this->datoscab[$i],1,0,'C',1);		
        }

        // Cuerpo de la Factura
        $this->Ln();	
        $aquiY = $this->GetY() + 0.6;
        $this->SetY($aquiY);
        // Posición X de la esquina izquierda cuadro 
        $aquiX = $this->GetX()+8 ;

        $this->SetDrawColor(0,0,0);		
        $this->SetTextColor(0);
        for($i=0;$i<count($this->datoscab);$i++)
        {	
            $this->Rect($aquiX, $aquiY, $this->widths[$i], 125 /*155*/, 1, 'D');
            $aquiX += $this->widths[$i];
        }
    }

    //Pie de pagina
    function Footer()
    {
        //Posicion: a 3 cm del final
        $this->SetY(-30);
        $this->SetLineWidth(0.1);		
        $this->SetTextColor(0);
        $this->SetFont('LiberationSans','',8);
        if ($this->piepagina == true)
        {
            // Si existen Incluimos las Observaciones
            if ($this->fdf_observaciones != '')
            {
                $this->addObservaciones(substr($this->fdf_observaciones, 0, 250));
            }

            // Lineas de Impuestos
            $this->addLineasIva($this->fdf_lineasiva);		

            // Total factura
            $this->addTotal();
        } else {
            // Neto por Pagina
            $this->addNeto();		
        }
    }

    function Row($data, $ultimo='1')
    {
        $this->SetFont('LiberationSans','',8);

        // Guardamos la posicion Actual
        $x=$this->GetX()+8;
        $y=$this->GetY();
        // Imprimimos solo los campos numericos
        for($i=0;$i<count($data);$i++)
        {
            if ($i != $ultimo) // La descripcion del articulo la trataremos la ultima. Aqui no.
            {
                $w=$this->widths[$i];
                //var_dump($w,"w",$i); exit();
                if ($i == ($ultimo-1))
                {			
                    $this->SetXY($x,$y);
                    $x1 = $x+$w;
                    $x += $this->widths[$ultimo]+$w;
                } else {
                    $x += $w;
                }
                // Seleccionar Alineacion
                $a=isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
                // Seleccionar color
                $this->SetTextColor(0);				
                if(isset($this->colores[$i][0])) {$this->SetTextColor($this->colores[$i][0], $this->colores[$i][1], $this->colores[$i][2]);}
                // Escribimos el texto
                //var_dump ($x,$w,$i,$data[$i]);                    exit();
                $this->MultiCell($w,5,$data[$i],0,$a);
                // Fijamos la posicion a la derecha de la celda
                $this->SetXY($x,$y);
            }
        }

        // En Ultimo lugar escribimos La descripcion del articulo
        $this->SetXY($x1,$y);		
        $w=$this->widths[$ultimo];
        $a=isset($this->aligns[$ultimo]) ? $this->aligns[$ultimo] : 'L';		
        $this->MultiCell($w,5,$data[$ultimo],0,$a);		

        // Calcular la altura MAXIMA de la fila e ir a la siguiente línea
        $nb = 0;
        for($i=0;$i<count($data);$i++)
        {
            $nb = max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        }

        if (($this->lineaactual + $nb) > 25) // Mas de una Pagina
        {
            $nbp = intval(($this->lineaactual + $nb)/25);
            $this->lineaactual = ($this->lineaactual + $nb) - ($nbp*25);
        } else {
            if (($this->lineaactual + $nb) == 25) // Pagina completa
            {
                $this->AddPage($this->CurOrientation);
                $this->lineaactual = 1;				
            } else {
                $this->lineaactual = $this->lineaactual + $nb; // Una sola Pagina
            }
        }

        $h = 5 * $this->lineaactual;	
        $this->Ln($h);
        $this->SetY(100+$h); // Y=100 en base a la altura de la cabecera

        // Dibujamos una Linea Gris para separar los Articulos
        $aquiX=$this->GetX()+0.155+8;
        $aquiY=$this->GetY();
        $this->SetDrawColor(225,225,225);		
        for($i=0;$i<count($this->datoscab);$i++)
        {	
            $finX = $this->widths[$i]+$aquiX - 0.316;
            $this->Line($aquiX, $aquiY, $finX, $aquiY);
            $aquiX = $finX + 0.316;
        }
        $this->SetDrawColor(0,0,0);
        $this->SetTextColor(0);		
    }

    function NbLines($w,$txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
        $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 and $s[$nb-1]=="\n")
        $nb--;
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb)
        {
            $c=$s[$i];
            if($c=="\n")
            {
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep=$i;
            $l+=$cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                    $i++;
                }
                else
                $i=$sep+1;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }

    function RoundedRect($x, $y, $w, $h,$r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' or $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2f %.2f m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2f %.2f l', $xc*$k,($hp-$y)*$k ));

        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2f %.2f l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2f %.2f l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2f %.2f l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2f %.2f %.2f %.2f %.2f %.2f c ', $x1*$this->k, ($h-$y1)*$this->k,
        $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    // Uso:
    // set alpha to semi-transparency
    // $pdf->SetAlpha(0.5, 'Lighten');
    // draw jpeg image
    // $pdf->Image('imagen.jpg',30,30,40);
    // restore full opacity
    // $pdf->SetAlpha(1);
    //	
    // class AlphaPDF

    // alpha: real value from 0 (transparent) to 1 (opaque)
    // bm:    blend mode, one of the following:
    //          Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn,
    //          HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity
    function SetAlpha($alpha, $bm='Normal')
    {
        // set alpha for stroking (CA) and non-stroking (ca) operations
        $gs = $this->AddExtGState(array('ca'=>$alpha, 'CA'=>$alpha, 'BM'=>'/'.$bm));
        $this->SetExtGState($gs);
    }

    function AddExtGState($parms)
    {
        $n = count($this->extgstates)+1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    function SetExtGState($gs)
    {
        $this->_out(sprintf('/GS%d gs', $gs));
    }

    function _enddoc()
    {
        if(!empty($this->extgstates) && $this->PDFVersion<'1.4')
        $this->PDFVersion='1.4';
        parent::_enddoc();
    }

    function _putextgstates()
    {
        for ($i = 1; $i <= count($this->extgstates); $i++)
        {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_out('<</Type /ExtGState');
            $parms = $this->extgstates[$i]['parms'];
            $this->_out(sprintf('/ca %.3F', $parms['ca']));
            $this->_out(sprintf('/CA %.3F', $parms['CA']));
            $this->_out('/BM '.$parms['BM']);
            $this->_out('>>');
            $this->_out('endobj');
        }
    }

    function _putresourcedict()
    {
        parent::_putresourcedict();
        $this->_out('/ExtGState <<');
        foreach($this->extgstates as $k=>$extgstate)
        $this->_out('/GS'.$k.' '.$extgstate['n'].' 0 R');
        $this->_out('>>');
    }

    function _putresources()
    {
        $this->_putextgstates();
        parent::_putresources();
    }
    // END-class AlphaPDF

    // Girar Texto o Imagen
    function RotatedText($x,$y,$txt,$angle)
    {
        //Text rotated around its origin
        $this->Rotate($angle,$x,$y);
        $this->Text($x,$y,$txt);
        $this->Rotate(0);
    }

    function RotatedImage($file,$x,$y,$w,$h,$angle)
    {
        //Image rotated around its upper-left corner
        $this->Rotate($angle,$x,$y);
        $this->Image($file,$x,$y,$w,$h);
        $this->Rotate(0);
    }	

    function Rotate($angle,$x=-1,$y=-1)
    {
        if($x==-1)
            $x=$this->x;
        if($y==-1)
            $y=$this->y;
        if($this->angle!=0)
            $this->_out('Q');
        $this->angle=$angle;
        if($angle!=0)
        {
            $angle*=M_PI/180;
            $c=cos($angle);
            $s=sin($angle);
            $cx=$x*$this->k;
            $cy=($this->h-$y)*$this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
        }
    }

    function _endpage()
    {
        if($this->angle!=0)
        {
            $this->angle=0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
    // END - Girar Texto o Imagen

    // Factura
    function sizeOfText( $texte, $largeur )
    {
        $index    = 0;
        $nb_lines = 0;
        $loop     = TRUE;
        while ( $loop )
        {
            $pos = strpos($texte, "\n");
            if (!$pos)
            {
                $loop  = FALSE;
                $ligne = $texte;
            }
            else
            {
                $ligne  = substr( $texte, $index, $pos);
                $texte = substr( $texte, $pos+1 );
            }
            $length = floor( $this->GetStringWidth( $ligne ) );
            $res = 1 + floor( $length / $largeur) ;
            $nb_lines += $res;
        }
        return $nb_lines;
    }

    // Empresa
    function addSociete( $nom, $adresse, $email, $web )
    {
        $x1 = 10;
        $y1 = 42;
	  $altocuadro=40;
        $this->SetXY(18,33);
        $this->SetFont('LiberationSans','',10);
        $this->SetTextColor(0);
        $length = $this->GetStringWidth( $nom );
        $this->MultiCell(66,5,'Emisor: ');
        $this->SetXY(18,38);
        $this->SetFillColor(230,230,230);
        $this->MultiCell(82,$altocuadro,'',0,'R',1);
        $this->SetTextColor(0);
        $this->SetXY(20,41);
        $this->AddFont('LiberationSans','','LiberationSans-Regular.php');
        $this->SetFont('LiberationSans','B',12);
        $length = $this->GetStringWidth( $nom );
        $this->MultiCell(80,4,$nom,0,'L');
        $this->SetXY(20,45);
        $this->SetFont('LiberationSans','',10);
        $length = $this->GetStringWidth( $adresse );
        $this->MultiCell($length, 4, $adresse);

        if ($email != '')
        {
            $this->SetXY(20,64);	
            $this->SetFont('LiberationSans','',9);
            $this->Write(5,ucfirst($this->idioma->email).': ');
            $this->SetTextColor(0,0,255);
            $this->Write(5, $email, 'mailto:' . $email);
            $this->SetTextColor(0);		
            $this->SetFont('');
        }

        if ($web != '')
        {
            $this->SetXY(20,68);
            $this->SetFont('LiberationSans','',9);
            $this->Write(5,ucfirst($this->idioma->web).': ');
            $this->SetTextColor(0,0,255);
            $this->Write(5, $web, $web);
            $this->SetTextColor(0);
            $this->SetFont('');
        }
    }

    // Nombre y numero del documento
    function fact_dev( $libelle, $num )
    {
        $r1  = $this->w - 100;
        $r2  = $r1 + 90;
        $y1  = 12;
        $y2  = $y1 + 2;
        $mid = ($r1 + $r2 ) / 2;
            
	$texte  = '' . $num;       
                
        $this->SetFont( "LiberationSans", "B", 15 );
        $this->SetXY($this->w,10);
        if ($libelle=="Albaran"){
            $libelle = utf8_decode(ucfirst( $this->idioma->fix_html($this->idioma->albaran)));
        } else if ($libelle=="Pedido"){
            $libelle = utf8_decode(ucfirst( $this->idioma->fix_html($this->idioma->pedido)));
        } else  if ($libelle=="Proforma"){
            $libelle = utf8_decode(ucfirst( $this->idioma->fix_html($this->idioma->proforma)));
        } else  if ($libelle=="Compra"){
            $libelle = utf8_decode(ucfirst( $this->idioma->fix_html($this->idioma->compra)));
        } else 
        {
            $libelle = ucfirst($this->idioma->fix_html($this->idioma->presupuesto));
        }
        $this->Cell(0,6,$libelle,0,0,'R');
        $this->SetFont( "LiberationSans", "B", 12 );
        $this->SetXY($this->w,15);
        $this->Cell(0,6,$texte,0,0,'R');
    }

    function addDate( $date )
    {
        $r1  = $this->w - 80;
        $r2  = $r1 + 30;
        $y1  = 17;
        $y2  = $y1 ;
        $mid = $y1 + ($y2 / 2);
        if ($this->fdf_tipodocumento == 'Oferta'){
            $texte  = utf8_decode(ucfirst( $this->idioma->fix_html($this->idioma->presupuesto))) . ' ' . $this->idioma->fix_html($this->idioma->fecha) .': ' . $date;    
        } else if ($this->fdf_tipodocumento == 'Pedido'){
            $texte  = ucfirst( $this->idioma->fix_html($this->idioma->pedido)) . ' ' . ucfirst( $this->idioma->fix_html($this->idioma->fecha)) .': ' . $date;
        }else if ($this->fdf_tipodocumento == 'Albaran'){
            $texte  = utf8_decode(ucfirst( $this->idioma->fix_html($this->idioma->albaran))) . ' ' . $this->idioma->fix_html($this->idioma->fecha) .': ' . $date;    
        }else 
        {        
	    $texte = ucfirst( $this->idioma->fix_html($this->idioma->pedido)) . ' ' . ucfirst( $this->idioma->fix_html($this->idioma->fecha)) .': '  . $date;    
        }
        $this->SetXY($this->w,20);
        $this->SetFont('LiberationSans','', 9 );
        $this->Cell(0,4,$texte,0,0,'R');
    }

    function addVencimiento ( $date )
    {
        $r1  = $this->w - 50;
        $r2  = $r1 + 40;
        $y1  = 17;
        $y2  = $y1;
        $mid = $y1 + ($y2 / 2);
	  $texte  = 'Fecha vencimiento: ' . $date;  
        $this->SetXY($this->w,28);
        $this->SetFont( "LiberationSans", "u", 9);
        $this->Cell(0,4,$texte,0,0,'R');
    }

    function addClient( $ref )
    {
        $r1  = $this->w - 50;
        $r2  = $r1 + 40;
        $y1  = 17;
        $y2  = $y1;
        $mid = $y1 + ($y2 / 2);
        if ($this->fdf_tipodocumento == 'Compra'){
            $texte  = ucfirst( utf8_decode($this->idioma->fix_html($this->idioma->proveedor))) .': ' . $ref;      
        }
        else 
        {        
            $texte  = ucfirst( utf8_decode($this->idioma->fix_html($this->idioma->num_cliente))) .': ' . $ref;    
        }
	
        $this->SetXY($this->w,28);
        $this->SetFont('LiberationSans','', 9);
        $this->Cell(0,4,$texte,0,0,'R');
    }

    function addPageNumber( $page )
    {
        $r1  = $this->w - 100;
        $r2  = $r1 + 20;
        $y1  = 17;
        $y2  = $y1;
        $mid = $y1 + ($y2 / 2);
	  $texte  = ucfirst( utf8_decode($this->idioma->fix_html($this->idioma->pagina))) ." ". $page;    
        $this->SetXY($this->w,$this->h-10);
        $this->SetFont('LiberationSans','',8);
        $this->Cell(0,4,$texte,0,0,'R');
    }

    // Cliente - Direccion fiscal
    function addClientAdresse( $adresse )
    {
        $r1     = $this->w - 97;
        $y1     = 38;
        $this->SetXY($r1,33);
        $this->SetFont('LiberationSans','',8);
        $this->SetTextColor(0);
        $this->MultiCell(66,5,'Fiscal: ');
        $this->SetXY( $r1, $y1);
// This is our cordinates for X and Y
        // $this->Rect ($r1-2,42,89,40,'D');
        $this->SetFont('LiberationSans','',9);		
        $this->MultiCell( 87, 4, $adresse,1,'L');
    }
    // Cliente - Direccion entrega
    function addClientEntrega( $adresse )
    {
        $r1     = $this->w - 97;
        $y1     = 76;
        $this->SetXY($r1,71);
        $this->SetFont('LiberationSans','',8);
        $this->SetTextColor(0);
        if ($this->idioma->codidioma=="es_ES"){
            $this->MultiCell(66,5,'Entrega en: ');
        } else {
            $this->MultiCell(66,5,'Delivery at: ');
        }        
        $this->SetXY( $r1, $y1);
        // $this->Rect ($r1-2,70,89,40,'D');
        $this->SetFont('LiberationSans','',9);      
        $this->MultiCell( 87, 4, $adresse,1,'L');
    }

    // Forma de Pago - Modificado
    
    function addPago($mode) {
   	$numlineas = count($mode);
	  // default
        $r1 = 110;
        $r2 = $r1 + 90;
        $y1 = 250;
        $y2 = $y1 + 23;
        if ($numlineas < 3)
        {
                $r1 = 150;
                $r2 = $r1 + 50;
                $y1 = 250;
                $y2 = $y1 + 15;
        }
        $mid = $y1 + (($y2 - $y1) / 2);
        $this->Rect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $y1 + 5, $r2, $y1 + 5);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 1);      
        $this->SetFont( "LiberationSans", "B", 9);
        $this->Cell(10, 4, ucfirst( $this->idioma->fix_html($this->idioma->forma_pago)), 0, 0, "C");   
        $salto = 4;
        $just = 'L';
        if ($numlineas < 3)
        {
          $this->SetFont('LiberationSans','', 9);
          $this->SetXY($r1 + 3 , $y1 + 6);    
          $just = 'C';
        }
        else 
        {
          $this->SetFont('LiberationSans','', 8);
          $this->SetXY($r1 + 3 , $y1 + 6);
        }
        if (($this->fdf_tipodocumento != 'Albaran' && $this->fdf_tipodocumento != 'Compra'))        
        {
            foreach ($mode as $lin){
            $this->Cell($r2 - $r1 - 6, $salto, utf8_decode($lin), 0, 2, $just);}
        }
        else
        {
            $this->Cell($r2 - $r1 - 6, $salto, utf8_decode($mode[0]), 0, 2, $just);
        }
            
    }
    // Divisa
    function addDivisa( $divisa )
    {
        $r1  = 140;
        $r2  = $r1 + 30;
        $y1  = 80;
        $y2  = $y1+10;
        $mid = $y1 + (($y2-$y1) / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2-$y1), 2.5, 'D');
        $this->Line( $r1, $mid, $r2, $mid);
        $this->SetXY( $r1 + ($r2 - $r1)/2 - 5 , $y1+1 );
        $this->SetFont( "LiberationSans", "B", 9);
        $this->Cell(10,4, "DIVISA", 0, 0, "C");
        $this->SetXY( $r1 + ($r2-$r1)/2 - 5 , $y1 + 5 );
        $this->SetFont('LiberationSans','', 9);
        $this->Cell(10,5,$divisa, 0,0, "C");
    }

    // Pais
    function addPais($pais)
    {
        $r1  = 170;
        $r2  = $r1 + 30;
        $y1  = 80;
        $y2  = $y1+10;
        $mid = $y1 + (($y2-$y1) / 2);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2-$y1), 2.5, 'D');
        $this->Line( $r1, $mid, $r2, $mid);
        $this->SetXY( $r1 + ($r2 - $r1)/2 - 5 , $y1+1 );
        $this->SetFont( "LiberationSans", "B", 9);		
        $this->Cell(10, 4, "PAIS", '', '', "C");
        $this->SetXY( $r1 + ($r2-$r1)/2 - 5 , $y1 + 5 );		
        $this->SetFont('LiberationSans','', 9);
        $this->Cell(10, 5, $pais, '', '', "C");
    }

    // Incluir Observaciones	
    function addObservaciones($observa)
    {
        if ($this->fdf_salida){
            $y1=261.5;
        } else {
            $y1=258;
        }   
        $this->SetFont( "LiberationSans", "B", 8);
        $length = $this->GetStringWidth("Observaciones: " . $observa);
        $this->SetXY( 18, $y1/*$this->h - 37.5*/ );
        $texto = ucfirst( $this->idioma->fix_html($this->idioma->observaciones).': ');
        $this->Cell($length,0, $texto);
        $this->SetXY( 18, $y1+2/*$this->h - 37.5*/ );
        $this->SetFillColor(230,230,230);
        $this->SetFont('LiberationSans','I', 8);
        $this->MultiCell(90,4, $observa,'0','L','true');
    }

    // Incluir Lineas de Iva
    function addLineasIva($datos)
    {
        $r1  = 18;
        $y1  =  227;//$this->h - 30;

        if ($datos) 
        {
            if (count($datos) > 3) 
            {
                // Comentar o eliminar las siguientes 5 lineas para NO mostrar el error.
                $this->SetFont( "LiberationSans", "B", 10);
                $this->SetXY( $r1, $y1 + 8 );
                $this->Cell(8,4, "ERROR: Localizadas ".count($datos)." lineas de ".FS_IVA."... ", 0, '', "L");
                $this->SetXY( $r1, $y1 + 12 );
                $this->Cell(8,4, chr(161).chr(161).chr(161)." Esta plantilla SOLO puede detallar TRES lineas de ".FS_IVA." !!!", 0, '', "L");	                
            } else {
                for ($i=1; $i <= count($datos); $i++)
                {
                    if ($i == 1) { $y2  = $y1 + 6; }
                    if ($i == 2) { $y2  = $y1 + 10; }
                    if ($i == 3) { $y2  = $y1 + 14; }		
                    $this->SetFont( "LiberationSans", "B", 6);
                    $this->SetXY( $r1, $y2 );
                    $this->Cell(8,4, $datos[$i][0], 0, '', "L");
                    $this->Cell(17,4, $datos[$i][1], 0, '', "R");
                    $this->Cell(7,4, $datos[$i][2], 0, '', "R");
                    $this->Cell(18,4, $datos[$i][3], 0, '', "R");
                    $this->Cell(7,4, $datos[$i][4], 0, '', "R");
                    $this->Cell(18,4, $datos[$i][5], 0, '', "R");
                    $this->Cell(7,4, $datos[$i][6], 0, '', "R");
                    $this->Cell(18,4, $datos[$i][7], 0, '', "R");
                    $this->SetFont( "LiberationSans", "B", 6.5);		
                    $this->Cell(24,4, $datos[$i][8], 0, '', "R");
                }
            }
        }
    }

    function addNeto()
    {
        $r1  = $this->w - 70;
        $r2  = $r1 + 60;
        $y1  = 227 /*$this->h - 30*/;
        $y2  = $y1+20;
        $this->Rect($r1, $y1, ($r2 - $r1), ($y2-$y1),'D');
        $this->Line( $r1+15,  $y1, $r1+15, $y2);
        $this->Line( $r1+15, $y1+4, $r2, $y1+4);
        $this->SetFont( "LiberationSans", "B", 8);
        $this->SetXY( $r1+22, $y1 );
        $this->Cell(30,4, $this->fdf_divisa, 0, 0, "C");
        $this->SetFont( "LiberationSans", "B", 8);
        $this->SetXY( $r1, $y1+7 );
        
        $this->Cell(15,4, mb_strtoupper($this->idioma->fix_html($this->idioma->neto)), 0, 0, "C");    
                
        // Total Neto de la pagina
        $this->SetFont('LiberationSans','', 9);
        $this->SetXY( $r1+16, $y1+6.5 );
        $this->Cell(43,4, $this->neto, 0, 0, "C");

        // Suma y Sigue		
        $this->SetFont( "LiberationSans", "B", 6);
        $this->SetXY( $r1+16, $y1+13 );
        $this->MultiCell(43,3,mb_strtoupper($this->idioma->fix_html($this->idioma->suma_sigue)),0,'C');
    }

    function addTotal()
    {
        $this->SetFont( "LiberationSans", "B", 8);
        $r1  = 18;
        $r2  = $r1 + 125;
        $y1  = 227;//$this->h - 30;
        $y2  = $y1+20;
        $this->Rect($r1, $y1, ($r2 - $r1), ($y2-$y1),'D');
        $this->Line( $r1, $y1+4, $r2, $y1+4);
        $this->Line( $r1+8,  $y1+4, $r1+8, $y2);
        $this->Line( $r1+25, $y1, $r1+25, $y2);
        $this->Line( $r1+33,  $y1+4, $r1+33, $y2);		
        $this->Line( $r1+51, $y1, $r1+51, $y2);
        $this->Line( $r1+58,  $y1+4, $r1+58, $y2);
        $this->Line( $r1+76, $y1, $r1+76, $y2);
        $this->Line( $r1+83,  $y1+4, $r1+83, $y2);		
        $this->Line( $r1+101, $y1, $r1+101, $y2);
        $this->SetXY( $r1, $y1);
        $this->Cell(26,4, mb_strtoupper( $this->idioma->fix_html($this->idioma->neto)), 0, '', "C");
        $this->SetX( $r1+26 );
        $this->Cell(25,4, mb_strtoupper( $this->idioma->fix_html($this->idioma->iva)), 0, '', "C");
        $this->SetX( $r1+51 );
        $this->Cell(25,4, mb_strtoupper( $this->idioma->fix_html($this->idioma->rec_equiv)), 0, '', "C");
        $this->SetX( $r1+76 );
        $this->Cell(25,4, FS_IRPF, 0, '', "C");
        $this->SetX( $r1+101 );
        $this->Cell(24,4, mb_strtoupper( $this->idioma->fix_html($this->idioma->importes)), 0, '', "C");
        
        $r1  = $this->w - 65;
        $r2  = $r1 + 55;
        $y1  = 227;//$this->h - 30;
        $y2  = $y1+20;
        $this->SetLineWidth(0.15);		
        $this->Rect($r1, $y1, ($r2 - $r1), ($y2-$y1), 'D');
        $this->Line( $r1+15,  $y1, $r1+15, $y2);
        $this->Line( $r1+15, $y1+4, $r2, $y1+4);
        $this->SetFont( "LiberationSans", "B", 8);
        $this->SetXY( $r1+18, $y1 );
        $this->Cell(30,4, $this->fdf_divisa, 0, 0, "C");
        $this->SetFont( "LiberationSans", "B", 8);
        $this->SetXY( $r1, $y1+7 );
        $this->Cell(15,4, "TOTAL", 0, 0, "C");
        $this->SetLineWidth(0.1);

        // Total factura
        $this->SetFont( "LiberationSans", "B", 9);
        $this->SetXY( $r1+12, $y1+6.5 );
        $this->Cell(43,4, $this->fdf_numtotal, 0, 0, "C");
   
    }

    // Añadir agencia //
    function addAgencia($agen)
    {
        $y1  = 246;
        $x1  = 18;
        $x2  = $x1;
        $y2  = $y1+5;
        $mid = $y1 + (($y2-$y1) / 2);
        $texte  = ucfirst( $this->idioma->fix_html($this->idioma->transporte)) . ': ' . $agen;
        $this->SetXY($x1,$y2/*$h-41,5*/);
        $this->SetFont( "LiberationSans", "B",9);
        $this->Cell(0,5,$texte,0,0,'L');
    }
    
    //Añadir fecha expedición pedido.
    function addSalida($fechasalida)
    {
	$texte  = ucfirst( utf8_decode($this->idioma->fix_html($this->idioma->entrega))) . ': ' . $fechasalida;            
        $x1 = 18;
        $y1 = 255;
	$this->SetXY($x1,$y1/*$h-41,5*/);
        $this->SetFont( "LiberationSans", "BU",9);
        $this->Cell(0,5,$texte,0,0,'L');
    }

    function numtoletras($xcifra)
    {
        $xarray = array(0 => "Cero",
                1 => "UN", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE",
                "DIEZ", "ONCE", "DOCE", "TRECE", "CATORCE", "QUINCE", "DIECISEIS", "DIECISIETE", "DIECIOCHO", "DIECINUEVE",
                "VEINTI", 30 => "TREINTA", 40 => "CUARENTA", 50 => "CINCUENTA", 60 => "SESENTA", 70 => "SETENTA", 80 => "OCHENTA", 90 => "NOVENTA",
                100 => "CIENTO", 200 => "DOSCIENTOS", 300 => "TRESCIENTOS", 400 => "CUATROCIENTOS", 500 => "QUINIENTOS", 600 => "SEISCIENTOS", 700 => "SETECIENTOS", 800 => "OCHOCIENTOS", 900 => "NOVECIENTOS"
        );
    //
        $xcifra = trim($xcifra);
        $xlength = strlen($xcifra);
        $xpos_punto = strpos($xcifra, ".");
        $xaux_int = $xcifra;
        $xdecimales = "00";
        if (!($xpos_punto === false)) {
            if ($xpos_punto == 0) {
                $xcifra = "0" . $xcifra;
                $xpos_punto = strpos($xcifra, ".");
            }
            $xaux_int = substr($xcifra, 0, $xpos_punto); // obtengo el entero de la cifra a convertir
            $xdecimales = substr($xcifra . "00", $xpos_punto + 1, 2); // obtengo los valores decimales
        }

        $XAUX = str_pad($xaux_int, 18, " ", STR_PAD_LEFT); // ajusto la longitud de la cifra, para que sea divisible por centenas de miles (grupos de 6)
        $xcadena = "";
        for ($xz = 0; $xz < 3; $xz++) {
            $xaux = substr($XAUX, $xz * 6, 6);
            $xi = 0;
            $xlimite = 6; // inicializo el contador de centenas xi y establezco el límite a 6 dígitos en la parte entera
            $xexit = true; // bandera para controlar el ciclo del While
            while ($xexit) {
                if ($xi == $xlimite) { // si ya ha llegado al límite máximo de enteros
                    break; // termina el ciclo
                }

                $x3digitos = ($xlimite - $xi) * -1; // comienzo con los tres primeros digitos de la cifra, comenzando por la izquierda
                $xaux = substr($xaux, $x3digitos, abs($x3digitos)); // obtengo la centena (los tres dígitos)
                for ($xy = 1; $xy < 4; $xy++) { // ciclo para revisar centenas, decenas y unidades, en ese orden
                    switch ($xy) {
                    case 1: // checa las centenas
                        if (substr($xaux, 0, 3) < 100) { // si el grupo de tres dígitos es menor a una centena ( < 99) no hace nada y pasa a revisar las decenas

                        } else {
                            $key = (int) substr($xaux, 0, 3);
                            if (TRUE === array_key_exists($key, $xarray)){  // busco si la centena es número redondo (100, 200, 300, 400, etc..)
                                $xseek = $xarray[$key];
                                $xsub = $this->subfijo($xaux); // devuelve el subfijo correspondiente (Millón, Millones, Mil o nada)
                                if (substr($xaux, 0, 3) == 100)
                                    $xcadena = " " . $xcadena . " CIEN " . $xsub;
                                else
                                    $xcadena = " " . $xcadena . " " . $xseek . " " . $xsub;
                                $xy = 3; // la centena fue redonda, entonces termino el ciclo del for y ya no reviso decenas ni unidades
                            }
                            else { // entra aquí si la centena no es numero redondo (101, 253, 120, 980, etc.)
                                $key = (int) substr($xaux, 0, 1) * 100;
                                $xseek = $xarray[$key]; // toma el primer caracter de la centena y lo multiplica por cien y lo busca en el arreglo (para que busque 100,200,300, etc)
                                $xcadena = " " . $xcadena . " " . $xseek;
                            } // ENDIF ($xseek)
                        } // ENDIF (substr($xaux, 0, 3) < 100)
                        break;
                    case 2: // Chequear las decenas (con la misma lógica que las centenas)
                        if (substr($xaux, 1, 2) < 10) {

                        } else {
                            $key = (int) substr($xaux, 1, 2);
                            if (TRUE === array_key_exists($key, $xarray)) {
                                $xseek = $xarray[$key];
                                $xsub = $this->subfijo($xaux);
                                if (substr($xaux, 1, 2) == 20)
                                    $xcadena = " " . $xcadena . " VEINTE " . $xsub;
                                else
                                    $xcadena = " " . $xcadena . " " . $xseek . " " . $xsub;
                                $xy = 3;
                            } else {
                                $key = (int) substr($xaux, 1, 1) * 10;
                                $xseek = $xarray[$key];
                                if (20 == substr($xaux, 1, 1) * 10)
                                    $xcadena = " " . $xcadena . " " . $xseek;
                                else
                                    $xcadena = " " . $xcadena . " " . $xseek . " Y ";
                            } // ENDIF ($xseek)
                        } // ENDIF (substr($xaux, 1, 2) < 10)
                        break;
                    case 3: // Chequear las unidades
                        if (substr($xaux, 2, 1) < 1) { // si la unidad es cero, ya no hace nada

                        } else {
                            $key = (int) substr($xaux, 2, 1);
                            $xseek = $xarray[$key]; // obtengo directamente el valor de la unidad (del uno al nueve)
                            $xsub = $this->subfijo($xaux);
                            $xcadena = " " . $xcadena . " " . $xseek . " " . $xsub;
                        } // ENDIF (substr($xaux, 2, 1) < 1)
                        break;
                    } // END SWITCH
                } // END FOR
                $xi = $xi + 3;
            } // ENDDO

            if (substr(trim($xcadena), -5, 5) == "ILLON") // si la cadena obtenida termina en MILLON o BILLON, entonces le agrega al final la conjuncion DE
                $xcadena.= " DE";

            if (substr(trim($xcadena), -7, 7) == "ILLONES") // si la cadena obtenida en MILLONES o BILLONES, entoncea le agrega al final la conjuncion DE
                $xcadena.= " DE";

            // ----------- esta línea la puedes cambiar de acuerdo a tus necesidades o a tu país -------
            if (trim($xaux) != "") {
                switch ($xz) {
                    case 0:
                        if (trim(substr($XAUX, $xz * 6, 6)) == "1")
                            $xcadena.= "UN BILLON ";
                        else
                            $xcadena.= " BILLONES ";
                        break;
                    case 1:
                        if (trim(substr($XAUX, $xz * 6, 6)) == "1")
                            $xcadena.= "UN MILLON ";
                        else
                            $xcadena.= " MILLONES ";
                        break;
                    case 2:
                        if ($xcifra < 1) {
                            $xcadena = "CERO con $xdecimales/100";
                        }
                        if ($xcifra >= 1 && $xcifra < 2) {
                            $xcadena = "UNO con $xdecimales/100";
                        }
                        if ($xcifra >= 2) {
                            $xcadena.= " con $xdecimales/100";
                        }
                        break;
                } // endswitch ($xz)
            } // ENDIF (trim($xaux) != "")

            $xcadena = str_replace("VEINTI ", "VEINTI", $xcadena); // quito el espacio para el VEINTI, para que quede: VEINTICUATRO, VEINTIUN, VEINTIDOS, etc
            $xcadena = str_replace("  ", " ", $xcadena); // quito espacios dobles
            $xcadena = str_replace("UN UN", "UN", $xcadena); // quito la duplicidad
            $xcadena = str_replace("  ", " ", $xcadena); // quito espacios dobles
            $xcadena = str_replace("BILLON DE MILLONES", "BILLON DE", $xcadena); // corrigo la leyenda
            $xcadena = str_replace("BILLONES DE MILLONES", "BILLONES DE", $xcadena); // corrigo la leyenda
            $xcadena = str_replace("DE UN", "UN", $xcadena); // corrigo la leyenda
        } // ENDFOR ($xz)

        $xcadena = str_replace("UN MIL ", "MIL ", $xcadena); // quito el BUG de UN MIL
        return trim($xcadena);
    }

    // END FUNCTION

    function subfijo($xx)
    { // esta función genera un subfijo para la cifra
        $xx = trim($xx);
        $xstrlen = strlen($xx);
        if ($xstrlen == 1 || $xstrlen == 2 || $xstrlen == 3)
            $xsub = "";
        //
        if ($xstrlen == 4 || $xstrlen == 5 || $xstrlen == 6)
            $xsub = "MIL";
        //
        return $xsub;
    }
    //Añadido número pedido cliente.
    function addPedidocliente ( $ref)
    {
        $r1  = $this->w - 50;
        $r2  = $r1 + 40;
        $y1  = 17;
        $y2  = $y1;
        $mid = $y1 + ($y2 / 2);
        $texte  = ucfirst( $this->idioma->fix_html($this->idioma->pedido_cliente)) . ": ".$ref; 
        $this->SetXY($this->w,24);
        $this->SetFont( "LiberationSans", "U", 9);
        $this->Cell(0,4,$texte,0,0,'R');
    }    

    // END FUNCTION	
}

?>
