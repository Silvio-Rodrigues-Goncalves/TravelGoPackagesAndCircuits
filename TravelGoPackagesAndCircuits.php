<?php

/*
Plugin Name: Listagem Pacotes
Version: 1.0
Author:Gustavo Sapienza
Author URI: http://www.gustavosapienza.com.br/

This plugin inherits the GPL license from it's parent system, WordPress.
*/
// error_reporting(0);
// ini_set(“display_errors”, 0 );


add_action( 'init', 'register_shortcodes');

  Class Localizacao{
    public $destino = array();

    public function set_destino($array){
      array_push($this->destino, $array);
    }

    public function get_json_localizacao(){
      return array('Localizacao' => $this->destino);
    }

  }

function pacotes_function($array_param) {

  global $post;
  $post_slug = $post->post_name;


  extract(shortcode_atts(array(
        'location_type' => '',
        'keyword' => '',
        'parentid' => '',
        'regiao' => '',
        'tipo_destino' => '',
        'layout' => '',
        'tag' => '',
    ), $array_param));


    $dia = current_time( 'd', $gmt = "-3" );
    $hora = current_time( 'G', $gmt = "-3" );
    $minuto = current_time( 'i', $gmt = "-3" );


    $atualiza_cache_a_cada = $dia;

    if( get_site_option("atualizar".$post_slug.$array_param["layout"], false, false) != $atualiza_cache_a_cada || $_GET["atualizar"]) {

        $options = array(
          'features' => SOAP_SINGLE_ELEMENT_ARRAYS, //SOAP_SINGLE_ELEMENT_ARRAYS
          'trace' => false,
          'cache_wsdl' => WSDL_CACHE_NONE,
          'ssl_method' => SOAP_SSL_METHOD_SSLv23,
          'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
        );

        try {



        $client_v2 = new SoapClient('https://v2.travelagent.com.br/EPAPI/PlatformAPI.svc?wsdl',$options);




        } catch (Exception $e) {
          $client_v2 = new SoapClient('http://preprod-v2.travelagent.com.br/EPAPI/PlatformAPI.svc?wsdl',$options);


        }

         $parametros = array('SignIn' => array('req' => array('Signature'=>'epapi@agaxtur.com.br', 'Password'=>'Agaxtur@17#')));

         $dados_login_retornados = $client_v2->__soapCall('SignIn', $parametros);

         $LocationType = $array_param["location_type"];
         $Keyword = $array_param["keyword"];
         $ParentId = $array_param["parentid"];
         $Regiao = $array_param["regiao"];
         $TipoDestino = $array_param["tipo_destino"];
         
         $HasIata = "";

        $retorno_FindLocations =  get_findLocation($client_v2, $dados_login_retornados, $LocationType, $Keyword, $HasIata, $ParentId);

        


        $localizacao = new Localizacao();
        $localizacao->set_destino( array('Keyword' => $retorno_FindLocations->Keyword) );
        $localizacao->set_destino( array('c' => $retorno_FindLocations->Locations->LocationLight[0]->c ) );
        $localizacao->set_destino( array('d' => $retorno_FindLocations->Locations->LocationLight[0]->d ) );
        $localizacao->set_destino( array('i' => $retorno_FindLocations->Locations->LocationLight[0]->i ) );
        $localizacao->set_destino( array('id' => $retorno_FindLocations->Locations->LocationLight[0]->id ) );
        $localizacao->set_destino( array('pn' => $retorno_FindLocations->Locations->LocationLight[0]->pn ) );
        $localizacao->set_destino( array('t' => $retorno_FindLocations->Locations->LocationLight[0]->t ) );


        if($ParentId){
            $array_produtos = montar_html_listagem_de_produtos2($client_v2, $dados_login_retornados, $ParentId, '', '', '');
        }else{
            $array_produtos = montar_html_listagem_de_produtos2($client_v2, $dados_login_retornados, $retorno_FindLocations->Locations->LocationLight, '', '', '');
        }

        $ret_products = array_merge($localizacao->get_json_localizacao(),array('Produtos' => $array_produtos) );

  


        $return_string = "";

        $json = '"'.get_the_title().'":[';
        $jsontudo .= '';


        foreach($ret_products['Produtos'] as $item) {

          $retorno_FindLocations_IATA_ID =  get_findLocation($client_v2, $dados_login_retornados, "City", $item['cidades'][1], $HasIata, $ParentId);


          $puro = array("https", "www.agaxturviagens");
          $filtrado   = array("https", "parceiros.agaxturviagens");

          $link_pacote_parceiros = str_replace($puro, $filtrado, $item['PackageDetailsURL']);

          $link_pacote = $item['PackageDetailsURL'];

          $startDate = array();
          $endDate = array();



          $hoje = date("Y-m-d");

          if(strtotime( $startDate[0] ) <= strtotime( $hoje  )){

            $startDate[0] = date($hoje, strtotime("+1 day"));

          }

          if(strtotime( $endDate[0] ) <= strtotime( $hoje  )){

            $endDate[0] = date($hoje, strtotime("+360 day"));

          }


          $startDate[0] = date_format(date_create($startDate[0]),"d/m/Y");
          $endDate[0] = date_format(date_create($endDate[0]),"d/m/Y");

          if (strpos($link_pacote, 'Construido') !== false) {

        $link_pacote = 'https://www.agaxturviagens.com.br/transacional/masterpricer?SearchType=Package&BeginDeparture='.$startDate[0].'&EndDeparture=28/12/2019&Origin=SAO&OriginId=275279&Destination='.$item['LocationIATA'].'&DestinationId='.$retorno_FindLocations_IATA_ID->Locations->LocationLight[0]->id.'&Ages=30,30&Construido=0';


      }


          if($array_param["tag"] && !strstr($item['KeyWords'], $array_param["tag"] ) ){
            continue;
          };

          // Json
          $json .= '{';
          $json .= '"Cache":"dia:'.$dia. " - " .$hora. ":" .$minuto.'",';
          $json .= '"idDestino":"'.$LocationType.' - '.$ParentId.'",';
          $json .= '"Regiao":"'.$Regiao.'",';
          $json .= '"tipoDestino":"'.$TipoDestino.'",';
          $json .= '"Destino":"'.$item['cidades'][1].'",';
          $json .= '"siglaIATA":"'.$item['LocationIATA'].'",';
          $json .= '"LinkProduto":"'.$link_pacote.'",';
          $json .= '"KeyWords":"'.$item['KeyWords'].'",';
          $json .= '"TipoProduto":"'.$item['tipo_produto'].'",';
          $json .= '"PackageConfigurationId":"'.$item['PackageConfigurationId'].'",';
          $json .= '"FullUrl":"https://apgwvzblen.cloudimg.io/crop/250x298/webp-lossy-50/'.$item['FullUrl'].'",';
          //$json .= '"FullUrl":"'.$item['FullUrl'].'",';
          $json .= '"Name":"'.$item['Name'].'",';
          $json .= '"TipoMoeda":"'.$item['Tipomoeda'].'",';
          $json .= '"Noites":"'.$item['NightsQuantity'].'",';
          if($item['tipo_produto'] == "Tour"){
            $json .= '"PrecoPor":"apartamento",';
          }else{
            $json .= '"PrecoPor":"pessoa",';
          }
          $json .= '"TipoQuarto":"'.$item['descritivo'].'",';
          $json .= '"TotalAmountByPassenger":"'.$item['TotalAmountByPassenger'].'"}';
          if ($item === end($ret_products['Produtos'])) {
            $json .= '';
          }else{
            $json .= ',';
          }

          // JsonTudo
          $jsontudo .= '{';
          $jsontudo .= '"Cache":"dia:'.$dia. " - " .$hora. ":" .$minuto.'",';
          $jsontudo .= '"idDestino":"'.$LocationType.' - '.$ParentId.'",';
          $jsontudo .= '"Regiao":"'.$Regiao.'",';
          $jsontudo .= '"tipoDestino":"'.$TipoDestino.'",';
          $jsontudo .= '"siglaIATA":"'.$item['LocationIATA'].'",';
          $jsontudo .= '"Destino":"'.get_the_title().'",';
          $jsontudo .= '"LinkProduto":"'.$link_pacote.'",';
          $jsontudo .= '"KeyWords":"'.$item['KeyWords'].'",';
          $jsontudo .= '"TipoProduto":"'.$item['tipo_produto'].'",';
          $jsontudo .= '"PackageConfigurationId":"'.$item['PackageConfigurationId'].'",';
          $jsontudo .= '"FullUrl":"https://apgwvzblen.cloudimg.io/crop/250x298/webp-lossy-50/'.$item['FullUrl'].'",';
          //$jsontudo .= '"FullUrl":"'.$item['FullUrl'].'",';
          $jsontudo .= '"Name":"'.$item['Name'].'",';
          $jsontudo .= '"TipoMoeda":"'.$item['Tipomoeda'].'",';
          $jsontudo .= '"Noites":"'.$item['NightsQuantity'].'",';
          if($item['tipo_produto'] == "Tour"){
            $jsontudo .= '"PrecoPor":"apartamento",';
          }else{
            $jsontudo .= '"PrecoPor":"pessoa",';
          }
          $jsontudo .= '"TipoQuarto":"'.$item['descritivo'].'",';
          $jsontudo .= '"TotalAmountByPassenger":"'.$item['TotalAmountByPassenger'].'"}';
          $jsontudo .= ',';



          // Layout1
          if($array_param["layout"] == "layout1" || $array_param["layout"] == ""){
            $return_string .= "<a href='". $link_pacote_parceiros."' data-tags='".$item['KeyWords']."' class='produto ".$item['tipo_produto']." id-".$item['PackageConfigurationId']."'   style='background: transparent url(https://apgwvzblen.cloudimg.io/crop/250x298/webp-lossy-50/".$item['FullUrl'].") repeat scroll center center / cover' data-cache='".$atualiza_cache_a_cada."-".$hora.":".$minuto."'   data-preco='".str_replace(".","",$item['TotalAmountByPassenger'])."' >";
         
            $return_string .= "<span class='nome-produto'>".$item['Name']."<br><span class='descritivo-nome-produto'>".$item['descritivo']."</span></span>";
            if($item['tipo_produto'] == "Tour"){
              $return_string .= "<span class='a-partir-de'>A partir de: <br>por apto</span>";
            }else{
              $return_string .= "<span class='a-partir-de'>A partir de: <br>por pessoa</span>";
            }
            
            $return_string .= "<span class='preco'>".$item['Tipomoeda']. " " .$item['TotalAmountByPassenger']."</span>";
            $return_string .= "<span class='noites'>".$item['NightsQuantity']." Noites</span>";
            $return_string .= "</a>";
          }


        }

        $json .= ']';
        


        add_site_option("atualizar".$post_slug.$array_param["layout"], $atualiza_cache_a_cada);
        update_site_option( "atualizar".$post_slug.$array_param["layout"], $atualiza_cache_a_cada);

        add_site_option("pagina".$post_slug.$array_param["layout"], $return_string);
        update_site_option( "pagina".$post_slug.$array_param["layout"], $return_string);

        add_site_option("pagina".$post_slug.$array_param["parentid"], $return_string);
        update_site_option( "pagina".$post_slug.$array_param["parentid"], $return_string);

        add_site_option("json".$post_slug, $json);
        update_site_option( "json".$post_slug, $json);

        add_site_option("jsontudo".$post_slug, $jsontudo);
        update_site_option( "jsontudo".$post_slug, $jsontudo);
        

    }

    if($_GET["atualizar"]) {
        return get_site_option("json".$post_slug, false, false);
    }else{
        return get_site_option("pagina".$post_slug.$array_param["parentid"], false, false);
    }


}


function register_shortcodes(){
   add_shortcode('pacotes', 'pacotes_function');
}



function montar_html_listagem_de_produtos2($client, $dados_login_retornados, $getDestinoIATA, $getDestinoName, $ids_produtos, $array_palavras_chave){

  $num_vezes_parcelas = 10;


  $array_produtos = array();
  $count_produtos = 0;
  $array_destinos_listados_produto = array();
  $array_destinos_listados_geral = array();


if (is_array($getDestinoIATA)) {
 $val_DestinationId = $getDestinoIATA[0]->id;

  $body_pacotes = array(
           'GetProductsContent' =>  array(
                 'req' => array(
                    'SecurityContext' => get_object_vars ( $dados_login_retornados->SignInResult->SecurityContext ),
                    'ProductType' => 'HybridPackage',
                    //'DestinationIATA'  => is_array($getDestinoIATA) ? $getDestinoIATA->Locations->LocationLight[0]->id : $getDestinoIATA, //Ex: SAO, POA
                    'DestinationId'  => $val_DestinationId, //Ex: SAO, POA
                    'DestinationName'  => "" //Ex:  São Paulo, Porto Alegre
                 )
              )

           );

  $body_circuitos = array(
        'GetProductsContent' =>  array(
              'req' => array(
                 'SecurityContext' => get_object_vars ( $dados_login_retornados->SignInResult->SecurityContext ),
                 'ProductType' => 'Services', //Services = para retorno de serviços em geral
                 //'DestinationIATA'  => $val_DestinationIata, //Ex: SAO, POA
                 'DestinationId'  => $val_DestinationId,
                 //'DestinationName'  => '', //Ex:  São Paulo, Porto Alegre
                 'ServiceContentFilter' => array(
                    'ServiceType' => 'Tour',
                    'MaintainZeroedFares' => true
                  )
              )
           )

        );

}
else  {

  $body_pacotes = array(
           'GetProductsContent' =>  array(
                 'req' => array(
                    'SecurityContext' => get_object_vars ( $dados_login_retornados->SignInResult->SecurityContext ),
                    'ProductType' => 'HybridPackage',
                    //'DestinationIATA'  => $getDestinoIATA,
                    'DestinationId'  => $getDestinoIATA, //Ex: SAO, POA
                    'DestinationName'  => "" //Ex:  São Paulo, Porto Alegre
                 )
              )

           );

  $body_circuitos = array(
        'GetProductsContent' =>  array(
              'req' => array(
                 'SecurityContext' => get_object_vars ( $dados_login_retornados->SignInResult->SecurityContext ),
                 'ProductType' => 'Services',
                 'DestinationId'  => $getDestinoIATA,
                 'ServiceContentFilter' => array(
                    'ServiceType' => 'Tour',
                    'MaintainZeroedFares' => true
                  )
              )
           )

        );

}


     $lista_pacotes_retornados = $client->__soapCall('GetProductsContent', $body_pacotes);
     $pacotes = $lista_pacotes_retornados->GetProductsContentResult->HybridPackageContent->PackageDestinations->PackageDestination;

     $lista_circuitos_retornados = $client->__soapCall('GetProductsContent', $body_circuitos);
     $circuitos = $lista_circuitos_retornados->GetProductsContentResult->ServiceContent->Services->Service;




   if(is_array($circuitos)) {
     foreach ($circuitos as $circuitos_services) {

        $cidade_encontrada = false;

        $tour_id = $circuitos_services->Id;


        foreach ($circuitos_services->Locations->Location as $key => $Location) {

          if (is_array($getDestinoName) && $getDestinoName != "") {
            $cidade_encontrada = in_array($Location->Country, $getDestinoName) || in_array($Location->NamePortuguese, $getDestinoName);
          }

          if (is_string($getDestinoName) && $getDestinoName != "") {
          $cidade_encontrada = strpos($Location->Country, $getDestinoName) !== false || strpos($Location->NamePortuguese, $getDestinoName) !== false;

          }

          if ($cidade_encontrada)
            break;

        }




      if ($getDestinoIATA != "" || $getDestinoIATA == "" && $getDestinoName == "" || $getDestinoName != "" && $cidade_encontrada) {

        $array_destinos_listados_produto = array();
        foreach ($circuitos_services->Locations->Location as $key => $Location) {

          array_push($array_destinos_listados_produto, $Location->Country);
          array_push($array_destinos_listados_produto, $Location->NamePortuguese);

          array_push($array_destinos_listados_geral, $Location->Country);
          array_push($array_destinos_listados_geral, $Location->NamePortuguese);

        }

        $array_destinos_listados_produto = array_unique($array_destinos_listados_produto);

        $array_produtos[$count_produtos]["FullUrl"] = "https://www.agaxturviagens.com.br/wp-content/uploads/2016/09/no-image.png";
        $array_produtos[$count_produtos]["LocationIATA"] = "";

        $array_produtos[$count_produtos]['chamada-do-preco'] = "A partir de ";

        $array_produtos[$count_produtos]['disponivel'] = "";
        $array_produtos[$count_produtos]['disponivel_class'] = "";

        $num_de_tarifas = count($circuitos_services->Fares->Fare) > 0 ? count($circuitos_services->Fares->Fare) - 1 : 0;

        $num_de_tarifas_FareValue = count($circuitos_services->Fares->Fare[$num_de_tarifas]->FareValues->FareValue) > 1 ? 1 : 0;

        $array_produtos[$count_produtos]['cidades'] = $array_destinos_listados_produto;

        $startDate = array();
        $endDate = array();

        foreach ($circuitos_services->Fares->Fare as $key_v1 => $Fare_v1) {


          array_push( $startDate, date_format(date_create(str_replace("T00:00:00", "", $Fare_v1->BookStartDate)),"Y-m-d"));
          array_push( $endDate,  date_format(date_create(str_replace("T00:00:00", "", $Fare_v1->BookEndDate)),"Y-m-d"));


        }


        usort($startDate, 'date_compare_asc2');
        usort($endDate, 'date_compare_desc2');



        $hoje = date("Y-m-d");

        if(strtotime( $startDate[0] ) <= strtotime( $hoje  )){

          $startDate[0] = date($hoje, strtotime("+1 day"));

        }


        $startDate[0] = date_format(date_create($startDate[0]),"d/m/Y");
        $endDate[0] = date_format(date_create($endDate[0]),"d/m/Y");



        $array_produtos[$count_produtos]['PackageDetailsURL'] = 'https://www.agaxturviagens.com.br/transacional/masterpricer?SearchType=Tour&StartDate='.$startDate[0].'&EndDate='.$endDate[0].'&ServiceId='.$tour_id.'&PassengerRooms=0,0&Ages=30,30#v42';

        $array_produtos[$count_produtos]["Name"] = $circuitos_services->Name;


        $array_produtos[$count_produtos]["KeyWords"] = "";

        if (count($circuitos_services->ServiceKeyWords->ServiceKeyWord)) {
          foreach ($circuitos_services->ServiceKeyWords->ServiceKeyWord as $indx => $ServiceKeyWord) {

                $array_produtos[$count_produtos]["KeyWords"] .= $ServiceKeyWord->KeyWord;

                if ($indx < count($circuitos_services->ServiceKeyWords->ServiceKeyWord) - 1) {
                  $array_produtos[$count_produtos]["KeyWords"] .= ', ';
                }
          }
        }




        $array_produtos[$count_produtos]["Title img"] ="";



        $array_produtos[$count_produtos]["TotalAmountByPassenger"]= number_format(round(number_format($circuitos_services->Fares->Fare[$num_de_tarifas]->FareValues->FareValue[$num_de_tarifas_FareValue]->Amount, 2, '.', '')),0,'','.');

        $array_produtos[$count_produtos]["TotalAmountByPassengerNaoFormatado"]  = number_format($circuitos_services->Fares->Fare[$num_de_tarifas]->FareValues->FareValue[$num_de_tarifas_FareValue]->Amount, 2, ',', '.');


        $array_produtos[$count_produtos]["Tipomoeda"] = $circuitos_services->Currency;

          if ($circuitos_services->Currency == "BRL") {
               $array_produtos[$count_produtos]["Tipomoeda"] = "R$ ";
            }

          if ($circuitos_services->Currency == "EUR") {
               $array_produtos[$count_produtos]["Tipomoeda"] = "EUR ";
            }


        $array_produtos[$count_produtos]["PackageConfigurationId"] = $tour_id;



        if ($circuitos_services->Images->ServiceImage[0]->Url != "") {

            $array_produtos[$count_produtos]["FullUrl"] = str_replace("https://www.travelagent.com.br/uploadImages/https", "https", $circuitos_services->Images->ServiceImage[0]->Url);


        }

        if ((int) $circuitos_services->ConsumableDays > 1 && $circuitos_services->Fares->Fare[$num_de_tarifas]->FareValues->FareValue[$num_de_tarifas_FareValue]->Title != "") {

          $array_produtos[$count_produtos]["descritivo"] = $circuitos_services->Fares->Fare[$num_de_tarifas]->FareValues->FareValue[$num_de_tarifas_FareValue]->Title;


        }

        else {

              $array_produtos[$count_produtos]['disponivel_class'] = "indisponivel"; //Sem preco, oculta
              $array_produtos[$count_produtos]['disponivel'] = "style=display:none;";

        }


        $array_produtos[$count_produtos]["NightsQuantity"] = (int) $circuitos_services->ConsumableDays - 1;


        $array_produtos[$count_produtos]["tipo_produto"] = $circuitos_services->ServiceType;




        $count_produtos++;
      }

     }
   }


    if(is_array($pacotes)) {
       foreach ( $pacotes as $chave_pacote => $package) {

        $cidade_encontrada = false;

        if (is_array($getDestinoName) && $getDestinoName != "") {
          $cidade_encontrada = in_array($package->LocationCountry, $getDestinoName) || in_array($package->LocationName, $getDestinoName);
        };

        if (is_string($getDestinoName) && $getDestinoName != "") {
        $cidade_encontrada = strpos($package->LocationCountry, $getDestinoName) !== false || strpos($package->LocationName, $getDestinoName) !== false;

        };




         if ($getDestinoIATA != "" || $getDestinoIATA == "" && $getDestinoName == "" || $getDestinoName != "" && $cidade_encontrada) {


        $array_destinos_listados_produto_pct = array();


        array_push($array_destinos_listados_produto_pct, $package->LocationCountry);
        array_push($array_destinos_listados_produto_pct, $package->LocationName);

        array_push($array_destinos_listados_geral, $package->LocationCountry);
        array_push($array_destinos_listados_geral, $package->LocationName);


        $array_destinos_listados_produto_pct = array_unique($array_destinos_listados_produto_pct);



            foreach ( $package->PackageConfigurations->PackageConfiguration as $chave => $destinos_pacotes) {



                $array_produtos[$count_produtos]["KeyWords"] = "";


                if (count($destinos_pacotes->PackageConfigurationKeywords->PackageConfigurationKeyWord)) {

                  foreach ($destinos_pacotes->PackageConfigurationKeywords->PackageConfigurationKeyWord as $indx2 => $PackageConfigurationKeyWord) {

                        $array_produtos[$count_produtos]["KeyWords"] .= $PackageConfigurationKeyWord->KeyWord;

                        if ($indx2 < count($destinos_pacotes->PackageConfigurationKeywords->PackageConfigurationKeyWord) - 1) {
                          $array_produtos[$count_produtos]["KeyWords"] .= ', ';
                        }
                  }
                }



               foreach ($destinos_pacotes->PackageConfigurationNights->PackageConfigurationNight as $key => $PackageConfigurationNight) {



          $array_produtos[$count_produtos]["Tipomoeda"] = "";
          $array_produtos[$count_produtos]["TotalAmountByPassenger"] = "";
          $array_produtos[$count_produtos]["TotalAmountByPassengerNaoFormatado"] = "";

                    $array_produtos[$count_produtos]["PackageDetailsURL"] = "";

                    $array_produtos[$count_produtos]["tipo_produto"] = "Pacote";

                  if (count($PackageConfigurationNight->PriceSummaries)) {


          $array_produtos[$count_produtos]["Name"] = $destinos_pacotes->Name;
          $array_produtos[$count_produtos]["NightsQuantity"] = $PackageConfigurationNight->NightsQuantity;



           $array_produtos[$count_produtos]["cidades"] = $array_destinos_listados_produto_pct;


          $array_produtos[$count_produtos]["LocationIATA"] = $package->LocationIATA;


                  if (count($PackageConfigurationNight->PackageConfigurationNightImages->PackageConfigurationNightImage)) {

                     $primary_image = $PackageConfigurationNight->PackageConfigurationNightImages->PackageConfigurationNightImage[0];

                     $array_produtos[$count_produtos]["FullUrl"] = $primary_image->FullUrl;
                     $array_produtos[$count_produtos]["Title img"] = $primary_image->Title;


                  }

                  else {


                     $array_produtos[$count_produtos]["FullUrl"] = "https://www.agaxturviagens.com.br/wp-content/uploads/2016/09/no-image.png";
                     $array_produtos[$count_produtos]["Title img"] = $primary_image->Title;

                  }

                    

             $package_id = $PackageConfigurationNight->PackageConfigurationId;


                     $PriceSummary = $PackageConfigurationNight->PriceSummaries->PackageConfigurationNightPriceSummary[0];


                     $array_produtos[$count_produtos]["PackageDetailsURL"] = $PriceSummary->PackageDetailsURL != "" ? "https://www.agaxturviagens.com.br". $PriceSummary->PackageDetailsURL : 'Construido';

                      $array_produtos[$count_produtos]["link_produto"] = $PriceSummary->PackageDetailsURL != "" ? "https://www.agaxturviagens.com.br". $PriceSummary->PackageDetailsURL : "#2";


                    $array_produtos[$count_produtos]['disponivel_class'] = "indisponivel"; //Sem preco, oculta
                    $array_produtos[$count_produtos]['disponivel'] = "style=display:none;";

                    $array_produtos[$count_produtos]['chamada-do-preco'] = "";
                    $array_produtos[$count_produtos]["Tipomoeda"] =  "Clique para consultar valores";


                  $array_produtos[$count_produtos]["PackageConfigurationId"] = $PackageConfigurationNight->PackageConfigurationId;

                     $tipos_moeda = "";

                     if (count($PackageConfigurationNight->PriceSummaries->PackageConfigurationNightPriceSummary)) {

                        if ($PriceSummary->Currency == "BRL") {
                           $tipos_moeda = "R$ ";
                        }

                        if ($PriceSummary->Currency == "USD") {
                           $tipos_moeda = "USD ";
                        }

                        if ($PriceSummary->TotalAmountByPassenger != "") {
                          $array_produtos[$count_produtos]['chamada-do-preco'] = "A partir de ";
                          $array_produtos[$count_produtos]["Tipomoeda"] = $tipos_moeda;


                          $array_produtos[$count_produtos]["TotalAmountByPassenger"] = number_format(round(number_format($PriceSummary->TotalAmountByPassenger, 2, '.', '')),0,'','.');

                          $array_produtos[$count_produtos]["TotalAmountByPassengerNaoFormatado"]  = number_format($PriceSummary->TotalAmountByPassenger, 2, ',', '.');



                        }

                        $array_produtos[$count_produtos]['disponivel'] = "";
                        $array_produtos[$count_produtos]['disponivel_class'] = "";

                     }

                  }

                  $count_produtos++;

               }


            };


         }

      }
    }

    return $array_produtos;



};

//##
function date_compare_asc2($a, $b)
{
    return strtotime($a) - strtotime($b);
}
function date_compare_desc2($a, $b)
{
    return strtotime($b) - strtotime($a);
}




function get_findLocation($client_v3, $dados_login_retornados, $locationType2, $Keyword = "#Vazio", $HasIata = true, $Parentid){


      $body_FindLocations = array(
               'FindLocations' =>  array(
                     'req' => array(
                        'SecurityContext' => get_object_vars ( $dados_login_retornados->SignInResult->SecurityContext ),
                        'ParentId' => $Parentid,
                        'Culture' => 'pt-BR',
                        'IdealAmountResults' => '1',
                        'IncludeAirportsChildren' => false,
                        'Filter' => array(
                           'HasHotelCode' => false,
                           'HasIata' => $HasIata,
                           'LoadProximities' => false,
                           'LocationTypes' => array(
                              'LocationType' => $locationType2,
                              )
                        ),
                        'AllowCitiesWithoutIATA' => false,
                        'LoadCityCodes' => false,
                        'Keyword' => $Keyword
                     )
                  )

               );


      $retorno_loc = $client_v3->__soapCall('FindLocations', $body_FindLocations)->FindLocationsResult;



      return isset($retorno_loc->Errors->Error) ? "#Erro no findLocation" : $retorno_loc;


}





?>
