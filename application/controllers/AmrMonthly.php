<?php
defined('BASEPATH') OR exit('No direct script access allowed');

set_time_limit(0);

class AmrMonthly extends CI_Controller {

	private $date;

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Asia/Jakarta');
		$this->load->model(array('Amr_model', 'amr_model'));
		$this->date = '2026-06-01';
	}

	public function dpo()
	{
		$this->setup();

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Data DPO');

		$header = array('ipd_id', 'patient_name', 'patient_id', 'patient_baby', 'patient_gender', 'patient_dob', 'patient_age', 'patient_weight', 'bed_group_name', 'icd10_registration', 'icd10_eklaim', 'diagnosa_awal', 'diagnosa_utama', 'komorbiditas_lain', 'registration_date', 'discharge_date', 'discharge_status', 'reference', 'monoterapi', 'kombinasi', 'dosis', 'frekuensi', 'periode', 'depo', 'medicine_name', 'pharmacy_resep_started', 'pharmacy_resep_finished', 'route', 'total_billing', 'harga_jual', 'antibiotik_cost', 'eklaim_code', 'eklaim_description', 'eklaim_tarif', 'asal_pasien', 'asal_rujukan');
		$row = 1;
		$this->write_excel_row($sheet, $row++, $header);

		for ($i = new DateTime($this->date); $i <= new DateTime(date("Y-m-t", strtotime($this->date))); $i->modify('+1 day')) {
			$start_date = $i->format('Y-m-d');
			$end_date = $i->format('Y-m-d');

			$day_rows = array();

			try {
				$query_one = $this->amr_model->patient_ipd($start_date, $end_date);

				foreach ($query_one as $row) {
					if ($row->ipd_status == 'Selesai ADM') {
						$pharmacy_dpo_checked = $this->amr_model->pharmacy_dpo_checked($row->ipd_id);

						if ( ! $pharmacy_dpo_checked) {
							$day_rows[] = $this->dpo_row_plain($row);
						}
					}
				}

				$query_two = $this->amr_model->patient_dpox($start_date, $end_date);

				foreach ($query_two as $row) {
					if ($row->ipd_status == 'Selesai ADM') {
						$day_rows[] = $this->dpo_row_detailed($row);
					}
				}
			} catch (Exception $e) {
				$this->amr_model->init_shared_db();
				echo 'Warning ' . $start_date . ': ' . $e->getMessage() . "<br>\n";
			}

			foreach ($day_rows as $data) {
				$this->write_excel_row($sheet, $row++, $data);
			}
		}

		$this->download($spreadsheet, 'Data DPO ' . $this->date . '.xlsx');
	}

	public function laboratorium()
	{
		$this->setup();

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Data Laboratorium');

		$header = array('ipd_id', 'speciment', 'culture_date', 'result_date', 'result');
		$row = 1;
		$this->write_excel_row($sheet, $row++, $header);

		for ($i = new DateTime($this->date); $i <= new DateTime(date("Y-m-t", strtotime($this->date))); $i->modify('+1 day')) {
			$start_date = $i->format('Y-m-d');
			$end_date = $i->format('Y-m-d');

			$day_rows = array();

			try {
				$ipd_list = $this->amr_model->discharged_ipd_ids($start_date, $end_date);

				foreach ($ipd_list as $ipd_row) {
					$order_labor = $this->amr_model->order_labor_mk_ipd($ipd_row->ipd_id);

					$row_data = array($ipd_row->ipd_id);
					foreach ($order_labor as $row) {
						$row_data[] = $row->spesimen;
						$row_data[] = $row->culture_date;
						$row_data[] = $row->result_date;
						$row_data[] = $row->result;
					}

					if (count($row_data) > 1) {
						$day_rows[] = $row_data;
					}
				}
			} catch (Exception $e) {
				$this->amr_model->init_shared_db();
				echo 'Warning ' . $start_date . ': ' . $e->getMessage() . "<br>\n";
			}

			foreach ($day_rows as $data) {
				$this->write_excel_row($sheet, $row++, $data);
			}
		}

		$this->download($spreadsheet, 'Data Laboratorium ' . $this->date . '.xlsx');
	}

	public function operasi()
	{
		$this->setup();

		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Data Operasi');

		$header = array('ipd_id', 'nama_operasi', 'tanggal_mulai', 'tanggal_selesai');
		$row = 1;
		$this->write_excel_row($sheet, $row++, $header);

		for ($i = new DateTime($this->date); $i <= new DateTime(date("Y-m-t", strtotime($this->date))); $i->modify('+1 day')) {
			$start_date = $i->format('Y-m-d');
			$end_date = $i->format('Y-m-d');

			$day_rows = array();

			try {
				$ipd_list = $this->amr_model->discharged_ipd_ids($start_date, $end_date);

				foreach ($ipd_list as $ipd_row) {
					$laporan_operasi = $this->amr_model->laporan_operasi_ipd($ipd_row->ipd_id);

					$row_data = array($ipd_row->ipd_id);
					foreach ($laporan_operasi as $row) {
						$row_data[] = $row->aok_nama_operasi ?? '';
						$row_data[] = $row->aok_operasi_start ?? '';
						$row_data[] = $row->aok_operasi_finish ?? '';
					}

					if (count($row_data) > 1) {
						$day_rows[] = $row_data;
					}
				}
			} catch (Exception $e) {
				$this->amr_model->init_shared_db();
				echo 'Warning ' . $start_date . ': ' . $e->getMessage() . "<br>\n";
			}

			foreach ($day_rows as $data) {
				$this->write_excel_row($sheet, $row++, $data);
			}
		}

		$this->download($spreadsheet, 'Data Operasi ' . $this->date . '.xlsx');
	}

	private function dpo_row_plain($row)
	{
		$case_reference_id = $row->case_reference_id;
		$ipd_id = $row->ipd_id;

		$total_billing = $this->amr_model->total_billing_ipd($ipd_id);
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

		return array(
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
			'',
			'',
			$row->dosis,
			$row->frekuensi,
			$row->periode,
			'',
			$row->medicine_name,
			'',
			'',
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
	}

	private function dpo_row_detailed($row)
	{
		$case_reference_id = $row->case_reference_id;
		$ipd_id = $row->ipd_id;

		$nama_depo = $this->amr_model->nama_depo($row->pharmacy_gudang_id);
		$total_billing = $this->amr_model->total_billing_ipd($ipd_id);
		$monoterapi_kombinasi = $this->amr_model->monoterapi_kombinasi($ipd_id);
		$pharmacy_resep_date = $this->amr_model->pharmacy_dpo_finished($ipd_id, $row->pharmacy_id);
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

		return array(
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
	}

	private function setup()
	{
		$directoryPath = FCPATH . 'amr';

		if ( ! is_dir($directoryPath)) {
			mkdir($directoryPath, 0777, TRUE);
		}

		require_once FCPATH . 'vendor/autoload.php';

		$this->amr_model->init_shared_db();
	}

	private function download($spreadsheet, $file_name)
	{
		$directoryPath = FCPATH . 'amr';

		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
		$writer->save($directoryPath . DIRECTORY_SEPARATOR . $file_name);

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment; filename="' . $file_name . '"');

		readfile($directoryPath . DIRECTORY_SEPARATOR . $file_name);

		exit;
	}

	private function clean_newlines($str, $replace = '. ')
	{
		if (empty($str)) {
			return $str;
		}

		$search = array("\r\n", "\n", "\r", "|");

		$cleaned = str_replace($search, $replace, $str);

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
