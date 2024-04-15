@php
    $dohLogo=public_path('/images/dohlogospaced.png');
    $jblLogo=public_path('/images/jblimslogo.png');
    $cpgLogo=public_path('/images/cpg.png');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interhospital Referral Form</title>
    <style>
         .footer {
         position: fixed;
         left: 20;
         bottom: 10;
         width: 100%;
         text-align: center;
         font-size: 12px;
         }
         table {
         width: 100%;
         }
         /* table, th,td, tr{
         border: 1px solid black;
         border-collapse:collapse;
         font-size: 12px;
         } */
         td{
            vertical-align: top;
         padding-top: 0.3rem;
         font-size: 12px;
         text-align: justify;
         }
         .underlined{
            text-decoration:underline
         }
         .col-title{
         font-size: .7rem;
         text-transform: uppercase;
         }
         .signatory{
         font-size: 1rem;
         text-transform: uppercase;
         text-align: left !important;
         }
         .checks{
         font-family: Arial, Helvetica, sans-serif;
         font-size: 1.3rem;
         }
         .footer table,
         .footer th,
         .footer td,
         .footer tr {
         border: none;
         border-collapse:collapse;
         margin: 1rem;
         }
         .tableResults{
         margin:0 !important;
         padding:0 !important; 
         }
         .tableResults td{
         height: 10px;
         }
         .page-break {
    page-break-after: always;
}
      </style>
</head>
<body>
      <div style='text-align:center;'>
      <img src="data:image/png;base64,{{ base64_encode(file_get_contents($dohLogo)) }}" width="140px" height="70px" style="float:left;">
      <img src="data:image/png;base64,{{ base64_encode(file_get_contents($jblLogo)) }}" width="140px" height="70px" style="float:right;">

         <p style='font-size:10px; margin: 0;'>Republic of the Philippines
            <br/>
            DEPARTMENT OF HEALTH
            <br>
            Central Luzon Center for Health Development
            <br>
            <b style='font-size:10px;'>JOSE B. LINGAD MEMORIAL GENERAL HOSPITAL</b><br/>
         </p>
         
         <p style='font-size:10px; margin: 0;'>Dolores, City of San Fernando, Pampanga
            <br/>
            Tel. No.: (045) 409-6688
         </p>
         <br>
         <b style='font-size:12px;'>INTERHOSPITAL REFERRAL FORM</b><br><br>
      </div>
      
      <table>
         <tr>
            <td width = "11rem">Referring Hospital or Facility:</td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->referringHospitalDescription}}</td>
         </tr>
         <tr>
            <td width = "11rem">Contact Number of Hospital: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->referrerContact}}</td>
         </tr>
         <tr>
            <td width = "11rem">Referring Physician: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->referringDoctor}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td height = "2rem"></td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "5rem">Name: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->lastName}}, {{$data->firstName}} {{$data->middleName}}</td>
            <td width = "5rem">Age/Gender: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->Age}}/{{$data->Gender}}</td>
         </tr>
         <tr>
            <td width = "5rem">Address: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->street}}, {{$data->barangayDesc}}, {{$data->municipalityDesc}}, {{$data->provinceDesc}}</td>
            <td width = "5rem">Contact No: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->patientContact}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td height = "2rem"></td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Date of Referral: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->referralDate}}</td>
            <td width = "9rem">Time of Referral: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->referralTime}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Chief Complaint: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->chiefComplaint}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Working Impression: </td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000;">{{$data->impression}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Reason for Referral: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->reasonForReferral}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Intended Department: </td>
            <td style = "border-bottom: 0.5px solid #000;">{{$data->assignedDepartment}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Pertinent History: </td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000;">{{$data->history}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td width = "9rem">Pertinent P.E: </td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000;">{{$data->examinationFindings}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td style = "text-align: left;"width = "9rem">Diagnostics Done: (Attach as needed)</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000;">{{$data->diagnosticsDone}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td style = "text-align: left;"width = "9rem">Medications Given: (Indicate last dose)</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000;">{{$data->medicalInterventions}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td style = "text-align: left;"width = "9rem">Procedure: </td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000;">{{$data->courseInTheWard}}</td>
         </tr>
      </table>
      <table>
         <tr>
            <td rowspan = "2" style = "text-align: left;"width = "9rem">Vital Signs Prior to Transfer: </td>
            <td width = "2rem">BP</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->systolic}}/{{$data->diastolic}}</b></td>
            <td width = "2rem">CR</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->cardiacRate}}</b></td>
            <td width = "2rem">RR</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->respiratoryRate}}</b></td>
            <td width = "4rem">O2 Sat</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->oxygenSaturation}}</b></td>
         </tr>
         <tr>
            <td width = "2rem">Temp</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->temperature}}</b></td>
            <td width = "2rem">CBG</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->cbg}}</b></td>
            <td width = "4rem">Pain Scale</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->painScale}}</b></td>
         </tr>
      </table>
      <table>
      <tr>
      <td rowspan = "2" style = "text-align: left;"width = "9rem"></td>
            <td width = "1rem">E</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->e}}</b></td>
            <td width = "1rem">V</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"><b>{{$data->v}}</b></td>
            <td width = "1rem">M</td>
            <td  style = "padding-bottom: -.1rem; border-bottom: 0.2px solid #000; text-align: center;"></b>{{$data->m}}</b></td>
            <td width = "1rem">GCS</td>
            10d>
         </tr>
      </table>
      <table>
         <tr>
            <td height = "1rem"></td>
         </tr>
      </table>
      <table>
         <tr>
            <td height = "2rem" style = "font-size: 0.5rem; font-style: italic; text-align:right;">Lifted and revised from Administrative Order 2020-019: Guidelines on the Service Delivery Design Health Care Provider Networks</td>
         </tr>
         <tr>
            <td height = "2rem" style = "font-size: 1rem; font-style: italic; text-align:center;">Sa JBLMGH: “Serbisyong may Lingap, Husay at Malasakit”</td>
         </tr>
         <tr>
            <td height = "2rem" style = "font-size: 1rem; font-style: italic; text-align:center;"></td>
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($cpgLogo)) }}" width='60px' height='30px'>
         </tr>
         <tr>
            <td height = "2rem" style = "font-size: 1rem; font-style: italic; text-align:center;"><b>ISO CERTIFIED HOSPITAL- QUALITY MANAGEMENT SYSTEM</b><br><p style = "font-size: 0.6rem;"><i>This document is a property of <b>JOSE B. LINGAD MEMORIAL GENERAL HOSPITAL</b> and the content are treated confidential therefore, unauthorized reproduction is strictly prohibited unless otherwise permitted by JBLMGH Top Management.</i></p></td>
         </tr>
      </table>
      @if(!empty($data->patientFiles))
    @foreach($data->patientFiles as $filename)
        <div class="page-break"></div>
        @php
            $imagePath = "C:\\Users\\IHOMS-TRISTAN\\Desktop\\jblmgh-ours-main\\src\\uploads\\{$data->referralID}\\{$filename}";
        @endphp
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($imagePath)) }}" width="100%" height="100%" style="float:left;">
    @endforeach
@endif

      </body>
</html>