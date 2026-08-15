<?php
defined('BASEPATH') or exit('No direct script access allowed');

set_time_limit(0);

header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class Amr extends CI_Controller
{

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->model(array('Amr_model', 'amr_model'));
	}

	public function index()
	{
		$data['title'] = 'Data AMR';
		$data['content'] = 'amr/index';
		$this->load->view('layout/wrapper', $data);
	}


	public function dpox()
	{
		$date = '2026-07-01';

		$startdate = new DateTime($date);
		$enddate = new DateTime(date("Y-m-t", strtotime($date)));

		$directoryPath = FCPATH . 'amr';

		if (! is_dir($directoryPath)) {
			mkdir($directoryPath, 0777, TRUE);
		}

		require_once FCPATH . 'vendor/autoload.php';

		$this->amr_model->init_shared_db();

		try {
			$header_amr = array('ipd_id', 'patient_name', 'patient_id', 'patient_baby', 'patient_gender', 'patient_dob', 'patient_age', 'patient_weight', 'bed_group_name', 'icd10_registration', 'icd10_eklaim', 'diagnosa_awal', 'diagnosa_utama', 'komorbiditas_lain', 'registration_date', 'discharge_date', 'discharge_status', 'reference', 'monoterapi', 'kombinasi', 'dosis', 'frekuensi', 'periode', 'depo', 'medicine_name', 'pharmacy_resep_started', 'pharmacy_resep_finished', 'route', 'total_billing', 'harga_jual', 'antibiotik_cost', 'eklaim_code', 'eklaim_description', 'eklaim_tarif', 'asal_pasien', 'asal_rujukan');
			$header_lab = array('ipd_id', 'speciment', 'culture_date', 'result_date', 'result');
			$header_ops = array('ipd_id', 'nama_operasi', 'tanggal_mulai', 'tanggal_selesai');

			$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
			$sheet_amr = $spreadsheet->getActiveSheet();
			$sheet_amr->setTitle('Data DPO');
			$sheet_lab = $spreadsheet->createSheet();
			$sheet_lab->setTitle('Data Laboratorium AMR');
			$sheet_ops = $spreadsheet->createSheet();
			$sheet_ops->setTitle('Data Operasi AMR');

			$row_amr = 1;
			$row_lab = 1;
			$row_ops = 1;

			$this->write_excel_row($sheet_amr, $row_amr++, $header_amr);
			$this->write_excel_row($sheet_lab, $row_lab++, $header_lab);
			$this->write_excel_row($sheet_ops, $row_ops++, $header_ops);

			for ($i = $startdate; $i <= $enddate; $i->modify('+1 day')) {
				$start_date = $i->format('Y-m-d');
				$end_date = $i->format('Y-m-d');

				$day_amr = array();
				$day_lab = array();
				$day_ops = array();

				try {
					// Semua Pasien Pulang Rawat Inap
					$query_one = $this->amr_model->patient_ipd($start_date, $end_date);
					// $query_one = $this->amr_model->patient_ipdx($start_date, $end_date);

					foreach ($query_one as $row) {
						if ($row->ipd_status == 'Selesai ADM') {
							$case_reference_id = $row->case_reference_id;
							$ipd_id = $row->ipd_id;

							$nama_depo = '';
							// $nama_depo = $this->amr_model->nama_depo($row->pharmacy_gudang_id);
							$total_billing = $this->amr_model->total_billing_ipd($ipd_id);
							$pharmacy_resep_date = '';
							$patient_weight = $this->amr_model->patient_weight($ipd_id);

							$diagnosa_awal = $this->amr_model->diagnosa_awal($ipd_id);
							$discharged_summary = $this->amr_model->discharged_summary($ipd_id);

							$rujukan_igd = $this->amr_model->rujukan_igd($case_reference_id);
							$rujukan_opd = $this->amr_model->rujukan_opd($case_reference_id);

							$asal_rujukan_igd = (isset($rujukan_igd->asal_rujukan)) ? $rujukan_igd->asal_rujukan : '';
							$asal_rujukan_opd = (isset($rujukan_opd->asal_rujukan)) ? $rujukan_opd->asal_rujukan : '';

							$asal_pasien = '';
							if ($asal_rujukan_igd != '') {
								$asal_pasien = 'IGD';
							} elseif ($asal_rujukan_opd != '') {
								$asal_pasien = 'OPD';
							}

							$asal_rujukan = ($asal_rujukan_igd != '') ? $asal_rujukan_igd : $asal_rujukan_opd;

							$monoterapi = '';
							$kombinasi = '';

							$data = array(
								$ipd_id,
								$row->patient_name,
								$row->patient_id,
								$row->patient_baby,
								$row->patient_gender,
								$row->patient_dob,
								$row->patient_age,
								(isset($patient_weight->patient_weight)) ? $patient_weight->patient_weight : '',
								$row->bed_group_name,
								$row->icd10_registration,
								$row->icd10_eklaim,
								$this->clean_newlines($diagnosa_awal->diagnosa_awal ?? ''),
								$this->clean_newlines($discharged_summary->diagnosa_utama ?? ''),
								$this->clean_newlines($discharged_summary->komorbiditas_lain ?? ''),
								$row->registration_date,
								$row->discharge_date,
								$row->discharge_status,
								$row->reference,
								$monoterapi,
								$kombinasi,
								$row->dosis,
								$row->frekuensi,
								$row->periode,
								$nama_depo,
								$row->medicine_name,
								$pharmacy_resep_date,
								$pharmacy_resep_date,
								$row->route,
								$total_billing,
								$row->harga_jual,
								$row->total_antibotik,
								$row->eklaim_code,
								$row->eklaim_description,
								$row->eklaim_tarif,
								$asal_pasien,
								$asal_rujukan
							);

							$pharmacy_dpo_checked = $this->amr_model->pharmacy_dpo_checked($ipd_id);

							if (!$pharmacy_dpo_checked) {
								$day_amr[] = $data;
							}

							// Mikrobiologi Klinik
							$order_labor_mk_ipd = $this->amr_model->order_labor_mk_ipd($ipd_id);

							$row_data = array($ipd_id);

							foreach ($order_labor_mk_ipd as $row) {
								$row_data[] = $row->spesimen;
								$row_data[] = $row->culture_date;
								$row_data[] = $row->result_date;
								$row_data[] = $row->result;
							}

							if (count($row_data) > 1) {
								$day_lab[] = $row_data;
							}

							// Laporan Operasi
							$laporan_operasi_ipd = $this->amr_model->laporan_operasi_ipd($ipd_id);

							$row_data = array($ipd_id);

							foreach ($laporan_operasi_ipd as $row) {
								$row_data[] = $row->aok_nama_operasi ?? '';
								$row_data[] = $row->aok_operasi_start ?? '';
								$row_data[] = $row->aok_operasi_finish ?? '';
							}

							if (count($row_data) > 1) {
								$day_ops[] = $row_data;
							}
						}
					}

					// Pasien Pulang Rawat Inap (DPO)
					$query_two = $this->amr_model->patient_dpox($start_date, $end_date);
					// $query_two = $this->amr_model->patient_medicine($start_date, $end_date);

					foreach ($query_two as $row) {
						if ($row->ipd_status == 'Selesai ADM') {
							$case_reference_id = $row->case_reference_id;
							$ipd_id = $row->ipd_id;

							$nama_depo = $this->amr_model->nama_depo($row->pharmacy_gudang_id);
							$total_billing = $this->amr_model->total_billing_ipd($ipd_id);
							$monoterapi_kombinasi = $this->amr_model->monoterapi_kombinasi($ipd_id);
							$pharmacy_resep_date = $this->amr_model->pharmacy_dpo_finished($ipd_id, $row->pharmacy_id);
							// $pharmacy_resep_date = $this->amr_model->pharmacy_resep_finished($ipd_id, $row->pharmacy_id);
							$patient_weight = $this->amr_model->patient_weight($ipd_id);

							$diagnosa_awal = $this->amr_model->diagnosa_awal($ipd_id);
							$discharged_summary = $this->amr_model->discharged_summary($ipd_id);

							$rujukan_igd = $this->amr_model->rujukan_igd($case_reference_id);
							$rujukan_opd = $this->amr_model->rujukan_opd($case_reference_id);

							$asal_rujukan_igd = (isset($rujukan_igd->asal_rujukan)) ? $rujukan_igd->asal_rujukan : '';
							$asal_rujukan_opd = (isset($rujukan_opd->asal_rujukan)) ? $rujukan_opd->asal_rujukan : '';

							$asal_pasien = '';
							if ($asal_rujukan_igd != '') {
								$asal_pasien = 'IGD';
							} elseif ($asal_rujukan_opd != '') {
								$asal_pasien = 'OPD';
							}

							$asal_rujukan = ($asal_rujukan_igd != '') ? $asal_rujukan_igd : $asal_rujukan_opd;

							if ($monoterapi_kombinasi->count_resep == 1) {
								$monoterapi = 'Ya';
							} else {
								$monoterapi = 'Tidak';
							}

							if ($monoterapi_kombinasi->count_resep > 1) {
								$kombinasi = 'Ya';
							} else {
								$kombinasi = 'Tidak';
							}

							$data = array(
								$ipd_id,
								$row->patient_name,
								$row->patient_id,
								$row->patient_baby,
								$row->patient_gender,
								$row->patient_dob,
								$row->patient_age,
								(isset($patient_weight->patient_weight)) ? $patient_weight->patient_weight : '',
								$row->bed_group_name,
								$row->icd10_registration,
								$row->icd10_eklaim,
								$this->clean_newlines($diagnosa_awal->diagnosa_awal ?? ''),
								$this->clean_newlines($discharged_summary->diagnosa_utama ?? ''),
								$this->clean_newlines($discharged_summary->komorbiditas_lain ?? ''),
								$row->registration_date,
								$row->discharge_date,
								$row->discharge_status,
								$row->reference,
								$monoterapi,
								$kombinasi,
								$row->dosis,
								$row->frekuensi,
								$row->periode,
								$nama_depo,
								$row->medicine_name,
								$pharmacy_resep_date->pharmacy_resep_started ?? '',
								$pharmacy_resep_date->pharmacy_resep_finished ?? '',
								$row->route,
								$total_billing,
								$row->harga_jual,
								$row->total_antibotik,
								$row->eklaim_code,
								$row->eklaim_description,
								$row->eklaim_tarif,
								$asal_pasien,
								$asal_rujukan
							);

							$day_amr[] = $data;

							// Mikrobiologi Klinik
							$order_labor_mk_ipd = $this->amr_model->order_labor_mk_ipd($ipd_id);

							$row_data = array($ipd_id);

							foreach ($order_labor_mk_ipd as $row) {
								$row_data[] = $row->spesimen;
								$row_data[] = $row->culture_date;
								$row_data[] = $row->result_date;
								$row_data[] = $row->result;
							}

							if (count($row_data) > 1) {
								$day_lab[] = $row_data;
							}

							// Laporan Operasi
							$laporan_operasi_ipd = $this->amr_model->laporan_operasi_ipd($ipd_id);

							$row_data = array($ipd_id);

							foreach ($laporan_operasi_ipd as $row) {
								$row_data[] = $row->aok_nama_operasi ?? '';
								$row_data[] = $row->aok_operasi_start ?? '';
								$row_data[] = $row->aok_operasi_finish ?? '';
							}

							if (count($row_data) > 1) {
								$day_ops[] = $row_data;
							}
						}
					}
				} catch (Exception $e) {
					// Koneksi DB putus di tengah hari ini: reconnect lalu lanjut hari berikutnya
					$this->amr_model->init_shared_db();
					echo 'Warning ' . $start_date . ': ' . $e->getMessage() . "<br>\n";
				}

				foreach ($day_amr as $data) {
					$this->write_excel_row($sheet_amr, $row_amr++, $data);
				}
				foreach ($day_lab as $data) {
					$this->write_excel_row($sheet_lab, $row_lab++, $data);
				}
				foreach ($day_ops as $data) {
					$this->write_excel_row($sheet_ops, $row_ops++, $data);
				}
			}

			$file_name_xlsx = 'Data AMR ' . $date . ' - ' . date("Y-m-t", strtotime($date)) . '.xlsx';

			$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
			$writer->save($directoryPath . DIRECTORY_SEPARATOR . $file_name_xlsx);

			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="' . $file_name_xlsx . '"');

			readfile($directoryPath . DIRECTORY_SEPARATOR . $file_name_xlsx);

			exit;
		} catch (Exception $e) {
			echo 'Message: ' . $e->getMessage();
		}
	}


	private function clean_newlines($str, $replace = '. ')
	{
		if (empty($str)) {
			return $str;
		}

		// Mengganti semua variasi newline dengan karakter pengganti
		// \r\n (Windows), \n (Linux/Unix), \r (Mac lama)
		$search = array("\r\n", "\n", "\r", "|");

		$cleaned = str_replace($search, $replace, $str);

		// Opsional: Membersihkan multiple spaces jika hasil replace menghasilkan spasi ganda
		return preg_replace('/\s+/', ' ', trim($cleaned));
	}

	private function write_excel_row($sheet, $row, $values)
	{
		foreach (array_values($values) as $col => $value) {
			$sheet->setCellValueExplicitByColumnAndRow(
				$col + 1,
				$row,
				(string)($value === null ? '' : $value),
				\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
			);
		}
	}
}
