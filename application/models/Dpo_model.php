<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 *
 * Model Dpo_model
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

class Dpo_model extends CI_Model
{

  // ------------------------------------------------------------------------
  private $_shared_db_live;
  private $_shared_db_rme;

  public function init_shared_db()
  {
    $this->_shared_db_live = $this->load->database('db_live', TRUE);
    $this->_shared_db_live->db_debug = FALSE;
    $this->_shared_db_rme  = $this->load->database('db_rme', TRUE);
    $this->_shared_db_rme->db_debug = FALSE;
    $this->_shared_db_live->query("SET SESSION wait_timeout = 28800");
    $this->_shared_db_rme->query("SET SESSION wait_timeout = 28800");
  }

  private function _check_alive($group)
  {
    $prop = ($group === 'db_rme') ? '_shared_db_rme' : '_shared_db_live';
    $db = $this->$prop;

    if (! $db->conn_id) {
      $db->initialize();
      $db->query("SET SESSION wait_timeout = 28800");
      return;
    }

    try {
      if ($db->conn_id->ping() === FALSE) {
        $db->conn_id = FALSE;
        $db->initialize();
        $db->query("SET SESSION wait_timeout = 28800");
      }
    } catch (\Throwable $e) {
      $db->conn_id = FALSE;
      $db->initialize();
      $db->query("SET SESSION wait_timeout = 28800");
    }
  }

  private function _db($group = 'db_live')
  {
    if ($group === 'db_rme' && isset($this->_shared_db_rme)) {
      $this->_check_alive('db_rme');
      return $this->_shared_db_rme;
    }
    if ($group === 'db_live' && isset($this->_shared_db_live)) {
      $this->_check_alive('db_live');
      return $this->_shared_db_live;
    }
    return $this->load->database($group, TRUE);
  }
  // ------------------------------------------------------------------------


  // ------------------------------------------------------------------------
  public function query_mutasi($startdate, $enddate)
  {
    // 
    $db_live = $this->_db('db_live');

    $db_live->select("
        ipd.patient_id,
        ipd.case_reference_id,
        ipd.id AS ipd_id,
        p.patient_name,
        IF(pb.patient_id IS NULL, 'Tidak', 'Ya') AS patient_baby,
        p.gender AS patient_gender,
        p.birth_date AS patient_dob,
        TIMESTAMPDIFF(YEAR, p.birth_date, ipd.date) AS patient_age,
        b.name AS bed_name,
        bg.name AS bed_name_group,
        CASE
            WHEN pbh.to_date IS NOT NULL
            THEN DATEDIFF(pbh.to_date, pbh.from_date)
            ELSE NULL
        END AS total_hari,
        dpolo.id_obat AS pharmacy_id,
        dpolo.nama_obat AS medicine_name,
        dpolo.rute AS route,
        DATE_FORMAT(ipd.date, '%Y-%m-%d') AS registration_date,
        DATE_FORMAT(dc.discharge_date, '%Y-%m-%d') AS discharge_date,
        IF(dc.discharge_status = 5, 'Wafat', 'Sehat') AS discharge_status,
        IF(ipd.asal_rujukan IS NULL, 'Tidak', 'Ya') AS reference
    ", FALSE);

    $db_live->from('ipd_details AS ipd');

    $db_live->join('patients AS p', 'ipd.patient_id = p.id');
    $db_live->join('patient_bed_history AS pbh', 'pbh.case_reference_id = ipd.case_reference_id');
    $db_live->join('discharge_card AS dc', 'dc.case_reference_id = ipd.case_reference_id');

    $db_live->join('bed AS b', 'pbh.bed_id = b.id', 'left');
    $db_live->join('bed_group AS bg', 'pbh.bed_group_id = bg.id', 'left');

    $db_live->join('db_rme.dpo AS dpo', 'ipd.id = dpo.dpo_ipd_id');
    $db_live->join('db_rme.dpo_list_obat AS dpolo', 'dpo.dpo_id = dpolo.dpo_id');

    $db_live->join('pharmacy_resep_detail AS prd', 'dpolo.resep_id_detail = prd.id');
    $db_live->join('pharmacy_resep AS pr', 'prd.pharmacy_resep_id = pr.id');
    $db_live->join('pharmacy_resep_link AS prl', 'prd.pharmacy_resep_link_id = prl.id');

    $db_live->join('patient_baby AS pb', 'p.id = pb.patient_id', 'left');

    $db_live->where('dc.discharge_date >=', $startdate);
    $db_live->where('dc.discharge_date <=', $enddate);

    $db_live->where('ipd.is_active', 'yes');
    $db_live->where('ipd.is_delete', 'no');
    $db_live->where('ipd.discharged', 'yes');

    $db_live->where_in('dpolo.id_obat', [
      305,
      306,
      322,
      324,
      325,
      326,
      327,
      328,
      399,
      400,
      418,
      419,
      658,
      667,
      707,
      708,
      709,
      861,
      875,
      1017,
      1166,
      1167,
      1176,
      1177,
      1210,
      1212,
      1253,
      1255,
      1256,
      1257,
      1328,
      1360,
      1361,
      1362,
      1378,
      1379,
      1568,
      1618,
      1712,
      1713,
      1714,
      1715,
      1717,
      1718,
      1719,
      1720,
      1721,
      1722,
      1723,
      1724,
      1725,
      1726,
      1768,
      1769,
      2078,
      2251,
      2265,
      3616,
      3801,
      3828,
      3878,
      3972,
      4410,
      4993
    ]);

    $db_live->group_by([
      'ipd.id',
      'dpolo.id_obat',
      'dpolo.tanggal_mulai'
    ]);

    $db_live->order_by('ipd.patient_id', 'DESC');

    $query = $db_live->get();
    return $query;
  }

  public function get_bed_mutasi($startdate, $enddate)
  {
    $db_live = $this->_db('db_live');

    $db_live->select("
        ipd.patient_id,
        ipd.case_reference_id,
        ipd.id AS ipd_id,
        pbh.id as bed_history_id,
        p.patient_name,
        IF(pb.patient_id IS NULL, 'Tidak', 'Ya') AS patient_baby,
        p.gender AS patient_gender,
        p.birth_date AS tanggal_lahir,
        TIMESTAMPDIFF(YEAR, p.birth_date, ipd.date) AS patient_age,
        b.name AS bed_name,
        bg.name AS bed_name_group,
        pbh.from_date,
        pbh.to_date,
        CASE
            WHEN pbh.to_date IS NOT NULL
            THEN DATEDIFF(pbh.to_date, pbh.from_date)
            ELSE NULL
        END AS total_hari,
        DATE_FORMAT(ipd.date, '%Y-%m-%d') AS registration_date,
        DATE_FORMAT(dc.discharge_date, '%Y-%m-%d') AS discharge_date,
        IF(dc.discharge_status = 5, 'Wafat', 'Sehat') AS discharge_status,
        IF(ipd.asal_rujukan IS NULL, 'Tidak', 'Ya') AS reference
    ", FALSE);

    $db_live->from('ipd_details AS ipd');

    $db_live->join(
      'patients AS p',
      'ipd.patient_id = p.id'
    );

    $db_live->join(
      'patient_bed_history AS pbh',
      'pbh.case_reference_id = ipd.case_reference_id'
    );

    $db_live->join(
      'discharge_card AS dc',
      'dc.case_reference_id = ipd.case_reference_id'
    );

    $db_live->join(
      'bed AS b',
      'pbh.bed_id = b.id',
      'left'
    );

    $db_live->join(
      'bed_group AS bg',
      'pbh.bed_group_id = bg.id',
      'left'
    );

    $db_live->join(
      'patient_baby AS pb',
      'p.id = pb.patient_id',
      'left'
    );

    $db_live->where('dc.discharge_date >=', $startdate);
    $db_live->where('dc.discharge_date <=', $enddate);

    $db_live->where('ipd.is_active', 'yes');
    $db_live->where('ipd.is_delete', 'no');
    $db_live->where('ipd.discharged', 'yes');
    $db_live->where('pbh.is_delete', 'no');

    $db_live->order_by('ipd.patient_id', 'DESC');

    return $db_live->get();
  }

  // ------------------------------------------------------------------------

}

/* End of file Dpo_model.php */
/* Location: ./application/models/Dpo_model.php */