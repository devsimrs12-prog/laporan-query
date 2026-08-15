<?php
defined('BASEPATH') or exit('No direct script access allowed');

set_time_limit(0);


/**
 *
 * Controller Laporan
 *
 * This controller for ...
 *
 * @package   CodeIgniter
 * @category  Controller CI
 * @author    Setiawan Jodi <jodisetiawan@fisip-untirta.ac.id>
 * @author    Raul Guerrero <r.g.c@me.com>
 * @link      https://github.com/setdjod/myci-extension/
 * @param     ...
 * @return    ...
 *
 */

class Laporan extends CI_Controller
{

  public function __construct()
  {
    parent::__construct();
    $this->load->helper('url');
    $this->load->model('Laporan_model');
  }

  public function index()
  {
    echo 'halo';
  }

  public function sql()
  {

    $db_live = $this->load->database('db_live', TRUE);

    $query = $db_live->query("SELECT b.patient_name,a.patient_id,a.case_reference_id,a.date 
        FROM ipd_details AS a JOIN patients AS b ON a.patient_id=b.id 
        WHERE a.patient_id = '01306325'");

    print_r($query->row());
  }

  public function eklaim()
  {
    $bulan = $this->input->post('bulan');

    if (empty($bulan) || ! preg_match('/^\d{4}-\d{2}$/', $bulan)) {
      //view form input bulan
      $this->load->view('laporan/eklaim');
      return;
    }

    $start_date = $bulan . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));

    require_once FCPATH . 'vendor/autoload.php';

    $rows = $this->Laporan_model->severity($start_date, $end_date);

    $all_codes = array();
    foreach ($rows as $row) {
      if (empty($row['eklaim_icd10'])) {
        continue;
      }
      $all_codes = array_merge($all_codes, explode('#', $row['eklaim_icd10']));
    }

    $icd10_map = $this->Laporan_model->icd10_lookup($all_codes);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Severity');

    $header = array('no_mr', 'no_kartu', 'nama_pasien', 'diagnosa_awal', 'kode_inacbgs', 'severity', 'tarif_rs', 'tarif_top_up', 'eklaim_tarif', 'eklaim_tarif2', 'diagnosa_sekunder');
    $this->write_excel_row($sheet, 1, $header);

    $row_num = 2;
    foreach ($rows as $row) {
      $descriptions = array();
      if (! empty($row['eklaim_icd10'])) {
        foreach (explode('#', $row['eklaim_icd10']) as $code) {
          $code = trim($code);
          if ($code === '') {
            continue;
          }
          $descriptions[] = isset($icd10_map[$code]) ? $code . ' - ' . $icd10_map[$code] : $code;
        }
      }
      $row['eklaim_icd10'] = implode(', ', $descriptions);

      $this->write_excel_row($sheet, $row_num++, $row);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="EKLAIM_' . $start_date . '_' . $end_date . '.xlsx"');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');

    exit;
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


/* End of file Laporan.php */
/* Location: ./application/controllers/Laporan.php */