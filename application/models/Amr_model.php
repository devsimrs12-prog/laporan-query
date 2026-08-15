<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Amr_model extends CI_Model {

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

		if ( ! $db->conn_id) {
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
	
	public function total_billing_ipd($ipd)
	{
		$db_live = $this->_db('db_live');
		$db_live->select('SUM(qty * patient_charges.standard_charge) AS total_billing');
		$db_live->from('patient_charges');
		$db_live->join('charges', 'patient_charges.charge_id = charges.id AND charges.is_tindakan_perawat = 0');
		$db_live->where('patient_charges.is_active', 1);
		$db_live->where('patient_charges.ipd_id', $ipd);
		$query = $db_live->get();
		return $query->row()->total_billing;
	}
	
	public function monoterapi_kombinasi($ipd)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				COUNT(pharmacy_resep.ipd_details_id) AS count_resep
			FROM
				pharmacy_resep
				JOIN pharmacy_resep_detail ON pharmacy_resep.id = pharmacy_resep_detail.pharmacy_resep_id
				AND pharmacy_resep_detail.is_active = "YES"
				JOIN pharmacy ON pharmacy_resep_detail.pharmacy_id = pharmacy.id
				AND pharmacy.id IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
			WHERE
				pharmacy_resep.ipd_details_id = "' . $ipd . '"
				AND pharmacy_resep.is_active = "YES"
				AND pharmacy_resep.status = 1
				-- 	AND pharmacy_resep.pharmacy_gudang_id = 6
			GROUP BY
				pharmacy_resep_detail.id
		');
		return $query->row();
	}
	
	public function pharmacy_resep_finished($ipd, $pharmacy_id)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				MIN(pharmacy_resep.created_at) AS pharmacy_resep_started,
				MAX(pharmacy_resep.created_at) AS pharmacy_resep_finished
			FROM
				pharmacy_resep
				JOIN pharmacy_resep_detail ON pharmacy_resep.id = pharmacy_resep_detail.pharmacy_resep_id
				AND pharmacy_resep_detail.is_active = "YES"
			WHERE
				pharmacy_resep.ipd_details_id = "' . $ipd . '"
				AND pharmacy_resep_detail.pharmacy_id =  "' . $pharmacy_id . '"
				AND pharmacy_resep.is_active = "YES"
				AND pharmacy_resep.status = 1
				-- 	AND pharmacy_resep.pharmacy_gudang_id = 6
		');
		return $query->row();
	}
	
	public function rujukan_igd($case_reference_id)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				CASE
					WHEN igd_triage.cara_datang = 1 THEN "Sendiri"
					WHEN igd_triage.cara_datang = 2 AND igd_triage.asal_rujukan_lainnya IS NULL OR igd_triage.asal_rujukan_lainnya = "" THEN rs_ppjk.NAMA_PPK
					WHEN igd_triage.cara_datang = 2 AND igd_triage.asal_rujukan_lainnya IS NOT NULL OR igd_triage.asal_rujukan_lainnya <> "" THEN igd_triage.asal_rujukan_lainnya
					WHEN igd_triage.cara_datang = 3 THEN "Diantar Polisi"
					WHEN igd_triage.cara_datang = 4 THEN igd_triage.cara_datang_lainnya
					ELSE "-"
				END AS asal_rujukan 
			FROM
				igd_details
				JOIN igd_triage ON igd_details.triage = igd_triage.id
				LEFT JOIN rs_ppjk ON igd_triage.asal_rujukan = rs_ppjk.NAMA_USER 
			WHERE
				igd_details.case_reference_id = "' . $case_reference_id . '"
		');
		return $query->row();
	}
	
	public function rujukan_opd($case_reference_id)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				sep_ppkasal AS asal_rujukan
			FROM
				opd_details
			WHERE
				case_reference_id = "' . $case_reference_id . '"
		');
		return $query->row();
	}
	
	public function patient_medicine($start_date, $end_date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				IF(patient_baby.patient_id IS NULL, "Tidak", "Ya") AS patient_baby,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				-- asesmen_perawat.ap_berat_badan AS patient_weight,
				""  AS patient_weight,
				bed_group.name AS bed_group_name,
				icd10.formatted_code AS icd10_registration,
				case_references.eklaim_icd10 AS icd10_eklaim,
				-- db_rme.asesmen_medis.am_diagnosa AS diagnosa_awal,
				-- discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				-- discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				"" AS diagnosa_awal,
				"" AS diagnosa_utama,
				"" AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				IF(pharmacy_resep_link.status_racikan = 0, (SELECT GROUP_CONCAT(pharmacy_resep_detail.dosis) FROM pharmacy_resep_detail WHERE pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id), pharmacy_resep_link.dosis_racikan) AS dosis,
				pharmacy_resep_link.frekuensi,
				pharmacy_resep_link.periode,
				pharmacy_resep.pharmacy_gudang_id,
				-- pharmacy_gudang.name_gudang AS depo,
				pharmacy.id AS pharmacy_id,
				pharmacy.medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				pharmacy_resep_route.kode_rute AS route,
				pharmacy_resep_detail.harga_jual,
				pharmacy_resep_detail.total_jual AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN icd10 ON ipd_details.diagnosa_awal = icd10.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				LEFT JOIN floor ON floor.id = bed_group.floor
				LEFT JOIN bed_type ON patient_bed_history.bed_type_id = bed_type.id
				-- AND bed_type.id IN (8, 9, 10, 11)
				LEFT JOIN patient_baby ON patients.id = patient_baby.patient_id
				-- LEFT JOIN db_rme.asesmen_medis ON ipd_details.id = asesmen_medis.am_ipd AND asesmen_medis.am_aktif = 1
				-- LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd
				JOIN pharmacy_resep ON ipd_details.id = pharmacy_resep.ipd_details_id
				AND pharmacy_resep.is_active = "YES"
				AND pharmacy_resep.status = 1
				-- AND pharmacy_resep.pharmacy_gudang_id = 6
				JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				JOIN pharmacy_resep_detail ON pharmacy_resep.id = pharmacy_resep_detail.pharmacy_resep_id
				AND pharmacy_resep_detail.is_active = "YES"
				JOIN pharmacy ON pharmacy_resep_detail.pharmacy_id = pharmacy.id
				AND pharmacy.id IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				-- LEFT JOIN medicine_therapy_class ON pharmacy.medicine_therapy_class_id = medicine_therapy_class.id
				-- AND medicine_therapy_class.therapy_class LIKE "%Antibakteri%"
				JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id AND pharmacy_resep_link.is_active = "YES"
				LEFT JOIN pharmacy_resep_route ON pharmacy_resep_link.rute = pharmacy_resep_route.id
				-- LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd AND asesmen_perawat.ap_active = 1
			WHERE
				discharge_card.discharge_date BETWEEN "' . $start_date . ' 00:00:00" 
				AND "' . $end_date . ' 23:59:59"
				AND ipd_details.is_active = "yes" 
				AND ipd_details.is_delete = "no"
				AND ipd_details.discharged = "yes"
				AND ipd_details.status = "Selesai ADM"
			GROUP BY
				pharmacy_resep_detail.id,
				pharmacy_resep_detail.pharmacy_id,
				pharmacy_resep.created_at
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function order_labor_mk_ipd($ipd)
	{
		$db_live = $this->_db('db_live');
		$db_live->select('referral_spesimen_mikrobiologi.rsm_nama AS spesimen, DATE_FORMAT(permintaan_labor_mikro.pml_order_waktu_pengambilan, "%Y-%m-%d") AS culture_date, DATE_FORMAT(permintaan_labor_mikro.plm_hasil_tanggal, "%Y-%m-%d") AS result_date, permintaan_labor_mikro.plm_hasil AS result');
		$db_live->from('permintaan_labor');
		$db_live->join('permintaan_labor_mikro', 'permintaan_labor.id = permintaan_labor_mikro.plm_id');
		$db_live->join('referral_pemeriksaan_mikrobiologi', 'permintaan_labor_mikro.plm_order_pemeriksaan = referral_pemeriksaan_mikrobiologi.rpm_id', 'left');
		$db_live->join('referral_spesimen_mikrobiologi', 'permintaan_labor_mikro.plm_order_spesimen = referral_spesimen_mikrobiologi.rsm_id', 'left');
		$db_live->where('permintaan_labor.ipd_id', $ipd);
		$db_live->where('permintaan_labor.approve', 1);
		$db_live->where('permintaan_labor.is_active', 1);
		$query = $db_live->get();
		return $query->result();
	}
	
	public function patient_medicines($patient_id, $date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				asesmen_perawat.ap_berat_badan AS patient_weight,
				bed_group.name AS bed_group_name,
				discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				IF(pharmacy_resep_link.status_racikan = 0, (SELECT GROUP_CONCAT(pharmacy_resep_detail.dosis) FROM pharmacy_resep_detail WHERE pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id), pharmacy_resep_link.dosis_racikan) AS dosis,
				pharmacy_resep_link.frekuensi,
				pharmacy_resep_link.periode,
				pharmacy_gudang.name_gudang AS depo,
				pharmacy.id AS pharmacy_id,
				pharmacy.medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				pharmacy_resep_route.kode_rute AS route,
				pharmacy_resep_detail.harga_jual,
				pharmacy_resep_detail.total_jual AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				LEFT JOIN floor ON floor.id = bed_group.floor
				LEFT JOIN bed_type ON patient_bed_history.bed_type_id = bed_type.id
				-- AND bed_type.id IN (8, 9, 10, 11)
				LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd
				JOIN pharmacy_resep ON ipd_details.id = pharmacy_resep.ipd_details_id
				AND pharmacy_resep.is_active = "YES"
				AND pharmacy_resep.status = 1
				-- AND pharmacy_resep.pharmacy_gudang_id = 6
				JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				JOIN pharmacy_resep_detail ON pharmacy_resep.id = pharmacy_resep_detail.pharmacy_resep_id
				AND pharmacy_resep_detail.is_active = "YES"
				JOIN pharmacy ON pharmacy_resep_detail.pharmacy_id = pharmacy.id
				AND pharmacy.id IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				-- LEFT JOIN medicine_therapy_class ON pharmacy.medicine_therapy_class_id = medicine_therapy_class.id
				-- AND medicine_therapy_class.therapy_class LIKE "%Antibakteri%"
				JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id
				AND pharmacy_resep_link.is_active = "YES"
				LEFT JOIN pharmacy_resep_route ON pharmacy_resep_link.rute = pharmacy_resep_route.id
				LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd
				AND asesmen_perawat.ap_active = 1
			WHERE
				patients.id = ' . $patient_id . '
				-- AND ipd_details.date >= "' . $date . ' 00:00:00" 
				-- AND discharge_card.discharge_date <= "' . $date . ' 23:59:59"
			GROUP BY
				pharmacy_resep_detail.id
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function patient_dpo($start_date, $end_date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				asesmen_perawat.ap_berat_badan AS patient_weight,
				db_rme.dpo.ruangan AS bed_group_name,
				discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				IF(pharmacy_resep_link.status_racikan = 0, (SELECT GROUP_CONCAT(pharmacy_resep_detail.dosis) FROM pharmacy_resep_detail WHERE pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id), pharmacy_resep_link.dosis_racikan) AS dosis,
				db_rme.dpo_list_obat.frekuensi,
				db_rme.dpo_list_obat.periode,
				pharmacy_resep.pharmacy_gudang_id,
				-- pharmacy_gudang.name_gudang AS depo,
				db_rme.dpo_list_obat.id_obat AS pharmacy_id,
				db_rme.dpo_list_obat.nama_obat AS medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				db_rme.dpo_list_obat.rute AS route,
				pharmacy_resep_detail.harga_jual,
				pharmacy_resep_detail.total_jual AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				-- LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				-- LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				-- LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				JOIN db_rme.dpo ON ipd_details.id = db_rme.dpo.dpo_ipd_id
				JOIN db_rme.dpo_list_obat ON db_rme.dpo.dpo_id = db_rme.dpo_list_obat.dpo_id
				AND db_rme.dpo_list_obat.id_obat IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				JOIN pharmacy_resep_detail ON db_rme.dpo_list_obat.resep_id_detail = pharmacy_resep_detail.id
				JOIN pharmacy_resep ON pharmacy_resep_detail.pharmacy_resep_id = pharmacy_resep.id
				JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id
				-- JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd
				LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd
				AND asesmen_perawat.ap_active = 1
			WHERE
				discharge_card.discharge_date BETWEEN "' . $start_date . ' 00:00:00" 
				AND "' . $end_date . ' 23:59:59"
				AND ipd_details.is_active = "yes" 
				AND ipd_details.is_delete = "no"
				AND ipd_details.discharged = "yes"
				AND ipd_details.status = "Selesai ADM"
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function nama_depo($gudang_id)
	{
		$db_live = $this->_db('db_live');
		$db_live->select('pharmacy_gudang.name_gudang AS depo');
		$db_live->from('pharmacy_gudang');
		$db_live->where('pharmacy_gudang.id', $gudang_id);
		$query = $db_live->get();
		return $query->row()->depo;
	}
	
	public function patient_dpos($patient_id, $date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				asesmen_perawat.ap_berat_badan AS patient_weight,
				db_rme.dpo.ruangan AS bed_group_name,
				discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				IF(pharmacy_resep_link.status_racikan = 0, (SELECT GROUP_CONCAT(pharmacy_resep_detail.dosis) FROM pharmacy_resep_detail WHERE pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id), pharmacy_resep_link.dosis_racikan) AS dosis,
				db_rme.dpo_list_obat.frekuensi,
				db_rme.dpo_list_obat.periode,
				pharmacy_resep.pharmacy_gudang_id,
				-- pharmacy_gudang.name_gudang AS depo,
				db_rme.dpo_list_obat.id_obat AS pharmacy_id,
				db_rme.dpo_list_obat.nama_obat AS medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				db_rme.dpo_list_obat.rute AS route,
				pharmacy_resep_detail.harga_jual,
				pharmacy_resep_detail.total_jual AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				-- LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				-- LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				-- LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				JOIN db_rme.dpo ON ipd_details.id = db_rme.dpo.dpo_ipd_id
				JOIN db_rme.dpo_list_obat ON db_rme.dpo.dpo_id = db_rme.dpo_list_obat.dpo_id
				AND db_rme.dpo_list_obat.id_obat IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				JOIN pharmacy_resep_detail ON db_rme.dpo_list_obat.resep_id_detail = pharmacy_resep_detail.id
				JOIN pharmacy_resep ON pharmacy_resep_detail.pharmacy_resep_id = pharmacy_resep.id
				JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id
				-- JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd
				LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd
				AND asesmen_perawat.ap_active = 1
			WHERE
				patients.id = ' . $patient_id . '
				-- AND ipd_details.date >= "' . $date . ' 00:00:00" 
				-- AND discharge_card.discharge_date <= "' . $date . ' 23:59:59"
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function patient_dpox($start_date, $end_date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				IF(patient_baby.patient_id IS NULL, "Tidak", "Ya") AS patient_baby,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				-- asesmen_perawat.ap_berat_badan AS patient_weight,
				""  AS patient_weight,
				db_rme.dpo.ruangan AS bed_group_name,
				icd10.formatted_code AS icd10_registration,
				case_references.eklaim_icd10 AS icd10_eklaim,
				-- db_rme.asesmen_medis.am_diagnosa AS diagnosa_awal,
				-- discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				-- discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				"" AS diagnosa_awal,
				"" AS diagnosa_utama,
				"" AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				IF(pharmacy_resep_link.status_racikan = 0, (SELECT GROUP_CONCAT(pharmacy_resep_detail.dosis) FROM pharmacy_resep_detail WHERE pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id), pharmacy_resep_link.dosis_racikan) AS dosis,
				db_rme.dpo_list_obat.frekuensi,
				db_rme.dpo_list_obat.periode,
				pharmacy_resep.pharmacy_gudang_id,
				-- pharmacy_gudang.name_gudang AS depo,
				db_rme.dpo_list_obat.id_obat AS pharmacy_id,
				db_rme.dpo_list_obat.nama_obat AS medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				db_rme.dpo_list_obat.rute AS route,
				pharmacy_resep_detail.harga_jual,
				pharmacy_resep_detail.total_jual AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif,
				ipd_details.status AS ipd_status
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN icd10 ON ipd_details.diagnosa_awal = icd10.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				-- LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				-- LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				-- LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				JOIN db_rme.dpo ON ipd_details.id = db_rme.dpo.dpo_ipd_id
				JOIN db_rme.dpo_list_obat ON db_rme.dpo.dpo_id = db_rme.dpo_list_obat.dpo_id
				-- AND db_rme.dpo_list_obat.id_obat IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				-- AND (dpo_list_obat.frekuensi <> "" || dpo_list_obat.frekuensi <> "0") AND (dpo_list_obat.periode <> "" || dpo_list_obat.periode <> "0")
				JOIN pharmacy_resep_detail ON db_rme.dpo_list_obat.resep_id_detail = pharmacy_resep_detail.id
				JOIN pharmacy_resep ON pharmacy_resep_detail.pharmacy_resep_id = pharmacy_resep.id
				JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id
				LEFT JOIN patient_baby ON patients.id = patient_baby.patient_id
				-- JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				-- LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd AND discharged_summary.md_aktif = 1
				-- LEFT JOIN db_rme.asesmen_medis ON ipd_details.id = asesmen_medis.am_ipd AND asesmen_medis.am_aktif = 1
				-- LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd AND asesmen_perawat.ap_active = 1
			WHERE
				discharge_card.discharge_date BETWEEN "' . $start_date . ' 00:00:00" 
				AND "' . $end_date . ' 23:59:59"
				AND ipd_details.is_active = "yes" 
				AND ipd_details.is_delete = "no"
				AND ipd_details.discharged = "yes"
				-- AND ipd_details.status = "Selesai ADM"
				AND db_rme.dpo_list_obat.id_obat IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
			GROUP BY
				ipd_details.id,
				db_rme.dpo_list_obat.id_obat,
				-- IF(pharmacy_resep_link.status_racikan = 0, (SELECT GROUP_CONCAT(pharmacy_resep_detail.dosis) FROM pharmacy_resep_detail WHERE pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id), pharmacy_resep_link.dosis_racikan),
				-- db_rme.dpo_list_obat.frekuensi,
				-- db_rme.dpo_list_obat.periode,
				dpo_list_obat.tanggal_mulai
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function patient_ipd($start_date, $end_date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				IF(patient_baby.patient_id IS NULL, "Tidak", "Ya") AS patient_baby,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				"" AS patient_weight,
				bed_group.name AS bed_group_name,
				icd10.formatted_code AS icd10_registration,
				case_references.eklaim_icd10 AS icd10_eklaim,
				-- db_rme.asesmen_medis.am_diagnosa AS diagnosa_awal,
				-- discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				-- discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				"" AS diagnosa_awal,
				"" AS diagnosa_utama,
				"" AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				"" AS dosis,
				"" AS frekuensi,
				"" AS periode,
				-- pharmacy_gudang.name_gudang AS depo,
				"" AS pharmacy_id,
				"" AS medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				"" AS route,
				"" AS harga_jual,
				"" AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif,
				IF(db_rme.dpo_list_obat.id_obat IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993) IS NULL, "", "Yes") AS antibiotik,
				ipd_details.status AS ipd_status
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN icd10 ON ipd_details.diagnosa_awal = icd10.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				LEFT JOIN floor ON floor.id = bed_group.floor
				LEFT JOIN bed_type ON patient_bed_history.bed_type_id = bed_type.id
				-- AND bed_type.id IN (8, 9, 10, 11)
				LEFT JOIN patient_baby ON patients.id = patient_baby.patient_id
				-- LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd
				-- JOIN pharmacy_resep ON ipd_details.id = pharmacy_resep.ipd_details_id
				-- AND pharmacy_resep.is_active = "YES"
				-- AND pharmacy_resep.status = 1
				-- AND pharmacy_resep.pharmacy_gudang_id = 6
				-- JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				-- JOIN pharmacy_resep_detail ON pharmacy_resep.id = pharmacy_resep_detail.pharmacy_resep_id
				-- AND pharmacy_resep_detail.is_active = "YES"
				-- JOIN pharmacy ON pharmacy_resep_detail.pharmacy_id = pharmacy.id
				-- AND pharmacy.id IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				-- LEFT JOIN medicine_therapy_class ON pharmacy.medicine_therapy_class_id = medicine_therapy_class.id
				-- AND medicine_therapy_class.therapy_class LIKE "%Antibakteri%"
				-- JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id
				-- AND pharmacy_resep_link.is_active = "YES"
				-- LEFT JOIN pharmacy_resep_route ON pharmacy_resep_link.rute = pharmacy_resep_route.id
				-- LEFT JOIN db_rme.asesmen_medis ON ipd_details.id = asesmen_medis.am_ipd AND asesmen_medis.am_aktif = 1
				-- LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd AND asesmen_perawat.ap_active = 1
				LEFT JOIN db_rme.dpo ON ipd_details.id = db_rme.dpo.dpo_ipd_id
				LEFT JOIN db_rme.dpo_list_obat ON db_rme.dpo.dpo_id = db_rme.dpo_list_obat.dpo_id
			WHERE
				discharge_card.discharge_date BETWEEN "' . $start_date . ' 00:00:00" 
				AND "' . $end_date . ' 23:59:59"
				AND ipd_details.is_active = "yes" 
				AND ipd_details.is_delete = "no"
				AND ipd_details.discharged = "yes"
				-- AND ipd_details.status = "Selesai ADM"
			GROUP BY
				ipd_details.id
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function patient_ipdx($start_date, $end_date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT
				case_references.id AS case_reference_id,
				ipd_details.id AS ipd_id,
				patients.patient_name,
				ipd_details.patient_id,
				IF(patient_baby.patient_id IS NULL, "Tidak", "Ya") AS patient_baby,
				patients.gender AS patient_gender,
				patients.birth_date AS patient_dob,
				TIMESTAMPDIFF(YEAR, patients.birth_date, ipd_details.date) AS patient_age,
				"" AS patient_weight,
				bed_group.name AS bed_group_name,
				icd10.formatted_code AS icd10_registration,
				case_references.eklaim_icd10 AS icd10_eklaim,
				-- db_rme.asesmen_medis.am_diagnosa AS diagnosa_awal,
				-- discharged_summary.md_diagnosa_utama AS diagnosa_utama,
				-- discharged_summary.md_komorbiditas_lain AS komorbiditas_lain,
				"" AS diagnosa_awal,
				"" AS diagnosa_utama,
				"" AS komorbiditas_lain,
				DATE_FORMAT(ipd_details.date, "%Y-%m-%d") AS registration_date,
				DATE_FORMAT(discharge_card.discharge_date, "%Y-%m-%d") AS discharge_date,
				IF(discharge_card.discharge_status = 5, "Wafat", "Sehat") AS discharge_status,
				IF(ipd_details.asal_rujukan IS NULL, "Tidak", "Ya") AS reference,
				"" AS monoterapi,
				"" AS kombinasi,
				"" AS dosis,
				"" AS frekuensi,
				"" AS periode,
				pharmacy_resep.pharmacy_gudang_id,
				-- pharmacy_gudang.name_gudang AS depo,
				"" AS pharmacy_id,
				"" AS medicine_name,
				"" AS pharmacy_resep_started,
				"" AS pharmacy_resep_finished,
				"" AS route,
				"" AS harga_jual,
				"" AS total_antibotik,
				case_references.eklaim_code,
				case_references.eklaim_description,
				case_references.eklaim_tarif,
				IF(pharmacy_resep_detail.pharmacy_id IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993) IS NULL, "", "Yes") AS antibiotik
			FROM
				ipd_details
				JOIN case_references ON ipd_details.case_reference_id = case_references.id
				JOIN icd10 ON ipd_details.diagnosa_awal = icd10.id
				JOIN patients ON ipd_details.patient_id = patients.id
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id, discharge_status, discharge_condition FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
				LEFT JOIN (SELECT * FROM patient_bed_history WHERE from_date IN (SELECT MAX( from_date) FROM patient_bed_history WHERE is_delete = "no" GROUP BY case_reference_id) GROUP BY case_reference_id) patient_bed_history ON patient_bed_history.case_reference_id = ipd_details.case_reference_id
				LEFT JOIN bed ON patient_bed_history.bed_id = bed.id
				LEFT JOIN bed_group ON patient_bed_history.bed_group_id = bed_group.id
				LEFT JOIN floor ON floor.id = bed_group.floor
				LEFT JOIN bed_type ON patient_bed_history.bed_type_id = bed_type.id
				-- AND bed_type.id IN (8, 9, 10, 11)
				LEFT JOIN patient_baby ON patients.id = patient_baby.patient_id
				-- LEFT JOIN db_rme.discharged_summary ON ipd_details.id = discharged_summary.md_ipd
				JOIN pharmacy_resep ON ipd_details.id = pharmacy_resep.ipd_details_id
				AND pharmacy_resep.is_active = "YES"
				AND pharmacy_resep.status = 1
				-- AND pharmacy_resep.pharmacy_gudang_id = 6
				-- JOIN pharmacy_gudang ON pharmacy_resep.pharmacy_gudang_id = pharmacy_gudang.id
				JOIN pharmacy_resep_detail ON pharmacy_resep.id = pharmacy_resep_detail.pharmacy_resep_id
				AND pharmacy_resep_detail.is_active = "YES"
				-- JOIN pharmacy ON pharmacy_resep_detail.pharmacy_id = pharmacy.id
				-- AND pharmacy.id IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
				-- LEFT JOIN medicine_therapy_class ON pharmacy.medicine_therapy_class_id = medicine_therapy_class.id
				-- AND medicine_therapy_class.therapy_class LIKE "%Antibakteri%"
				-- JOIN pharmacy_resep_link ON pharmacy_resep_detail.pharmacy_resep_link_id = pharmacy_resep_link.id
				-- AND pharmacy_resep_link.is_active = "YES"
				-- LEFT JOIN pharmacy_resep_route ON pharmacy_resep_link.rute = pharmacy_resep_route.id
				-- LEFT JOIN db_rme.asesmen_medis ON ipd_details.id = asesmen_medis.am_ipd AND asesmen_medis.am_aktif = 1
				-- LEFT JOIN db_rme.asesmen_perawat ON ipd_details.id = asesmen_perawat.ap_ipd AND asesmen_perawat.ap_active = 1
				-- LEFT JOIN db_rme.dpo ON ipd_details.id = db_rme.dpo.dpo_ipd_id
				-- LEFT JOIN db_rme.dpo_list_obat ON db_rme.dpo.dpo_id = db_rme.dpo_list_obat.dpo_id
			WHERE
				discharge_card.discharge_date BETWEEN "' . $start_date . ' 00:00:00" 
				AND "' . $end_date . ' 23:59:59"
				AND ipd_details.is_active = "yes" 
				AND ipd_details.is_delete = "no"
				AND ipd_details.discharged = "yes"
				AND ipd_details.status = "Selesai ADM"
			GROUP BY
				ipd_details.id
			ORDER BY
				ipd_details.id
		');
		return $query->result();
	}
	
	public function pharmacy_dpo_finished($ipd, $pharmacy_id)
	{
		$db_rme = $this->_db('db_rme');
		$query = $db_rme->query('
			SELECT
				MIN(tanggal_mulai) AS pharmacy_resep_started,
				MAX(tanggal_mulai) AS pharmacy_resep_finished
			FROM
				dpo_list_obat
			WHERE
				ipd_id = "' . $ipd . '"
				AND id_obat =  "' . $pharmacy_id . '"
		');
		return $query->row();
	}
	
	public function laporan_operasi_ipd($ipd)
	{
		$db_rme = $this->_db('db_rme');
		$query = $db_rme->query('
			SELECT
				aok_nama_operasi,
				aok_operasi_start,
				aok_operasi_finish
			FROM
				laporan_ok
			WHERE
				aok_ipd_id = "' . $ipd . '"
		');
		return $query->result();
	}
	
	public function patient_weight($ipd_id)
	{
		$db_rme = $this->_db('db_rme');
		$db_rme->select('ap_berat_badan AS patient_weight');
		$db_rme->from('asesmen_perawat');
		$db_rme->where('ap_ipd', $ipd_id);
		$db_rme->where('ap_active', 1);
		$db_rme->order_by('ap_id', 'desc');
		$query = $db_rme->get();
		return $query->row();
	}
	
	public function diagnosa_awal($ipd_id)
	{
		$db_rme = $this->_db('db_rme');
		$db_rme->select('am_diagnosa AS diagnosa_awal');
		$db_rme->from('asesmen_medis');
		$db_rme->where('am_ipd', $ipd_id);
		$db_rme->where('am_aktif', 1);
		$db_rme->order_by('am_id', 'desc');
		$query = $db_rme->get();
		return $query->row();
	}
	
	public function discharged_summary($ipd_id)
	{
		$db_rme = $this->_db('db_rme');
		$db_rme->select('md_diagnosa_utama AS diagnosa_utama, md_komorbiditas_lain AS komorbiditas_lain');
		$db_rme->from('discharged_summary');
		$db_rme->where('md_ipd', $ipd_id);
		$db_rme->where('md_aktif', 1);
		$db_rme->order_by('md_id', 'desc');
		$query = $db_rme->get();
		return $query->row();
	}
	
	public function pharmacy_dpo_checked($ipd)
	{
		$db_rme = $this->_db('db_rme');
		$query = $db_rme->query('
			SELECT
				DISTINCT dpo_id AS antibiotik
			FROM
				dpo_list_obat
			WHERE
				ipd_id = "' . $ipd . '"
				AND id_obat IN (305, 306, 322, 324, 325, 326, 327, 328, 399, 400, 418, 419, 658, 667, 707, 708, 709, 861, 875, 1017, 1166, 1167, 1176, 1177, 1210, 1212, 1253, 1255, 1256, 1257, 1328, 1360, 1361, 1362, 1378, 1379, 1568, 1618, 1712, 1713, 1714, 1715, 1717, 1718, 1719, 1720, 1721, 1722, 1723, 1724, 1725, 1726, 1768, 1769, 2078, 2251, 2265, 3616, 3801, 3828, 3878, 3972, 4410, 4993)
		');
		return $query->row();
	}

	public function discharged_ipd_ids($start_date, $end_date)
	{
		$db_live = $this->_db('db_live');
		$query = $db_live->query('
			SELECT DISTINCT
				ipd_details.id AS ipd_id
			FROM
				ipd_details
				LEFT JOIN (SELECT DISTINCT discharge_date, case_reference_id, ipd_details_id FROM discharge_card GROUP BY case_reference_id, ipd_details_id) discharge_card ON discharge_card.case_reference_id = ipd_details.case_reference_id 
				AND discharge_card.ipd_details_id = ipd_details.id
			WHERE
				discharge_card.discharge_date BETWEEN "' . $start_date . ' 00:00:00" 
				AND "' . $end_date . ' 23:59:59"
				AND ipd_details.is_active = "yes" 
				AND ipd_details.is_delete = "no"
				AND ipd_details.discharged = "yes"
				AND ipd_details.status = "Selesai ADM"
		');
		return $query->result();
	}
}