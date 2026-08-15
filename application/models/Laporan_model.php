<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 *
 * Model Laporan_model
 *
 * This Model for ...
 * 
 * @package		CodeIgniter
 * @category	Model
 * @author    Setiawan Jodi <jodisetiawan@fisip-untirta.ac.id>
 * @link      https://github.com/setdjod/myci-extension/
 * @param     ...
 * @return    ...
 *
 */

class Laporan_model extends CI_Model
{

  // ------------------------------------------------------------------------

  public function __construct()
  {
    parent::__construct();
  }

  // ------------------------------------------------------------------------


  // ------------------------------------------------------------------------
  public function severity($start_date, $end_date)
  {

    $db_live = $this->load->database('db_live', TRUE);

    $query = $db_live->query("
                              SELECT
                              patients.id AS no_mr,
                              patients.bpjs_number AS no_kartu,
                              patients.patient_name AS nama_pasien,
                              CONCAT( icd10.formatted_code, ' - ', icd10.long_desc ) AS kode_icd10,
                              case_references.eklaim_code AS kode_inacbgs,
                          IF( LOCATE('-', case_references.eklaim_code) > 0,
                              SUBSTRING_INDEX( case_references.eklaim_code, '-', -1 ),
                              case_references.eklaim_code
                          ) AS severity,
                              SUM( patient_charges.qty * patient_charges.standard_charge ) AS tarif_rs,
                              ( case_references.eklaim_drug_tariff
                                  + case_references.eklaim_investigation_tariff
                                  + case_references.eklaim_procedure_tariff
                                  + case_references.eklaim_prosthesis_tariff ) AS tarif_top_up,

                              CASE
                                  WHEN case_references.eklaim_tarif = 0 AND ipd_details.sep_hakKelas = 1 THEN case_references.tarif_alt_kls1
                                  WHEN case_references.eklaim_tarif = 0 AND ipd_details.sep_hakKelas = 2 THEN case_references.tarif_alt_kls2
                                  WHEN case_references.eklaim_tarif = 0 AND ipd_details.sep_hakKelas = 3 THEN case_references.tarif_alt_kls3
                                  ELSE case_references.eklaim_tarif
                              END AS eklaim_tarif,
                              case_references.eklaim_tarif AS eklaim_tarif2,
                              case_references.eklaim_icd10
                          FROM ipd_details
                              LEFT JOIN case_references ON ipd_details.case_reference_id = case_references.id
                              LEFT JOIN patients ON patients.id = ipd_details.patient_id
                              LEFT JOIN icd10 ON icd10.id = ipd_details.diagnosa_awal
                              LEFT JOIN (
                                  SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition
                                  FROM discharge_card
                                  GROUP BY case_reference_id, ipd_details_id
                              ) t3 ON t3.case_reference_id = ipd_details.case_reference_id
                                  AND t3.ipd_details_id = ipd_details.id
                              LEFT JOIN (
                                  SELECT * FROM patient_bed_history
                                  WHERE from_date IN (
                                      SELECT MAX( from_date ) FROM patient_bed_history WHERE is_delete = 'no' GROUP BY case_reference_id
                                  )
                                  GROUP BY case_reference_id
                              ) t2 ON t2.case_reference_id = ipd_details.case_reference_id
                              LEFT JOIN patient_charges ON ipd_details.id = patient_charges.ipd_id
                          WHERE DATE( t3.discharge_date ) BETWEEN '" . $start_date . "' AND '" . $end_date . "'
                              AND ipd_details.is_delete = 'no'
                              AND ipd_details.discharged = 'yes'
                              AND patient_charges.is_active = 1
                              AND ipd_details.is_active = 'yes'
                              AND ipd_details.status = 'Selesai ADM'
                          GROUP BY ipd_details.id
                          ORDER BY ipd_details.date ASC
                         ");
    return $query->result_array();
  }

  // ------------------------------------------------------------------------

  public function icd10_lookup($codes)
  {
    if (empty($codes)) {
      return array();
    }

    $db_live = $this->load->database('db_live', TRUE);

    $codes = array_map('trim', $codes);
    $codes = array_filter($codes, 'strlen');
    $codes = array_values(array_unique($codes));

    if (empty($codes)) {
      return array();
    }

    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $query = $db_live->query("SELECT formatted_code, long_desc FROM icd10 WHERE formatted_code IN ($placeholders)", $codes);

    $map = array();
    foreach ($query->result_array() as $row) {
      $map[$row['formatted_code']] = $row['long_desc'];
    }

    return $map;
  }

  // ------------------------------------------------------------------------

}

/* End of file Laporan_model.php */
/* Location: ./application/models/Laporan_model.php */