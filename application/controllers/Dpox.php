<?php
defined('BASEPATH') or exit('No direct script access allowed');


/**
 *
 * Controller Dpox
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

class Dpox extends CI_Controller
{

  public function __construct()
  {
    parent::__construct();
    date_default_timezone_set('Asia/Jakarta');
    $this->load->model(array('Amr_model', 'amr_model'));
    $this->load->model(array('Dpo_model', 'dpo_model'));
  }

  public function index()
  {
    $data['title'] = 'Data DPO';
    $this->load->view('dpox', $data);
  }

  public function query()
  {
    $tabel = '';
    $date = '2026-01-01';

    $startdate = new DateTime($date);
    $enddate = new DateTime(date("Y-m-t", strtotime($date)));

    $query = $this->dpo_model->get_bed_mutasi('2026-01-01', '2026-01-02');
    $data_bed = [];
    $data_obat = [];
    if ($query->num_rows() > 0) {
      # code...
      $tabel .= '<table class="table" border=1>
  <thead>
    <tr>
      <th scope="col">#</th>
      <th scope="col">No MR</th>
      <th scope="col">Case Reference</th>
      <th scope="col">IPD ID</th>
      <th scope="col">History ID</th>
      <th scope="col">Nama</th>
      <th scope="col">Tanggal Lahir</th>
      <th scope="col">Umur</th>
      <th scope="col">Tempat Tidur</th>
      <th scope="col">Ruangan</th>
      <th scope="col">Tanggal Masuk</th>
      <th scope="col">Tanggal Keluar</th>
      <th scope="col">Hari</th>
      <th scope="col">Obat</th>
    </tr>
  </thead>
  <tbody>';

      $no = 1;
      foreach ($query->result_array() as $key => $value) {
        $tabel_obat = '';
        $ipd_id = $value['ipd_id'];
        // $pharmacy_id = $value['pharmacy_id'];
        $bed_history_id = $value['bed_history_id'];

        $pharmacy_resep_date = $this->amr_model->pharmacy_dpo_finished_mutasi($ipd_id);
        $tabel_obat .= '<table border="1">
            <tbody>';
        if ($pharmacy_resep_date->num_rows() > 0) {
          foreach ($pharmacy_resep_date->result_array() as $obat) {
            $tabel_obat .= '<tr>
                <th width="300">' . $obat['nama_obat'] . '</th>
                <td width="200">' . $obat['pharmacy_resep_started'] . '</td>
              </tr>';
          }
        } else {
          $tabel_obat .= '<tr>
                <th width="500">Tidak ada pemberian antibiotik</th>
              </tr>';
        }
        $tabel_obat .=  '</tbody>
          </table>';

        $data_bed[$bed_history_id] = $value;
        $data_obat[$bed_history_id] = $pharmacy_resep_date;
        # code...
        $tabel .= '<tr>
          <th scope="row">' . $no . '</th>
          <td>' . $value['patient_id'] . '</td>
          <td>' . $value['case_reference_id'] . '</td>
          <td>' . $value['ipd_id'] . '</td>
          <td>' . $value['bed_history_id'] . '</td>
          <td>' . $value['patient_name'] . '</td>
          <td>' . $value['tanggal_lahir'] . '</td>
          <td>' . $value['patient_age'] . '</td>
          <td>' . $value['bed_name'] . '</td>
          <td>' . $value['bed_name_group'] . '</td>
          <td>' . $value['from_date'] . '</td>
          <td>' . $value['to_date'] . '</td>
          <td>' . $value['total_hari'] . '</td>
          <td>' . $tabel_obat . '</td>
        </tr>';
        $no++;
      }

      $tabel .= '</tbody>
</table>';
      // return [
      //   'bed' => $data_bed,
      //   'obat' => $data_obat,
      // ];
    } else {
      // return [
      //   'bed' => $data_bed,
      //   'obat' => $data_obat,
      // ];
    }
    echo $tabel;
    // echo '<pre>';
    // print_r($data_bed);
    // print_r($data_obat);
    // echo '</pre>';
  }

  public function export()
  {

    $bulan = $this->input->post('bulan');
    if (empty($bulan)) {
      //view form input bulan
      echo 'tanggal tidak boleh kosong,';
      // $this->load->view('laporan/eklaim');
      return;
    }
    $mount = explode(' - ', trim($bulan));

    $start = DateTime::createFromFormat('d/m/Y', trim($mount[0]));
    $end   = DateTime::createFromFormat('d/m/Y', trim($mount[1]));

    $start_date = $start->format('Y-m-d');
    $end_date   = $end->format('Y-m-d');
    // print_r($bulan);
    // print_r($mount);
    // echo '<br>';
    // print_r($start_date);
    // echo '<br>';
    // print_r($end_date);
    // exit();
    // $date = '2026-01-01';

    // $startdate = new DateTime($date);
    // $enddate = new DateTime(date("Y-m-t", strtotime($date)));


    $directoryPath = FCPATH . 'public/amr';

    if (!is_dir($directoryPath)) {
      mkdir($directoryPath, 0777, TRUE);
    }

    require_once FCPATH . 'vendor/autoload.php';

    $this->amr_model->init_shared_db();

    try {
      $header = array('No MR', 'Case Reference', 'IPD ID', 'Registration Date', 'Tanggal Pulang', 'Nama', 'Tanggal Lahir', 'Umur', 'Tempat Tidur', 'Ruangan', 'Bed Masuk', 'Bed Keluar', 'Hari', 'Obat');

      $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
      $sheet = $spreadsheet->getActiveSheet();
      $sheet->setTitle('Data DPO Mutasi');

      $title = 'LAPORAN DATA DPO MUTASI PASIEN PERIODE ' . $start_date . ' s/d ' . $end_date;
      $sheet->mergeCells('A1:M1');
      $sheet->setCellValue('A1', $title);
      $sheet->getStyle('A1')->getFont()->setBold(TRUE)->setSize(14);
      $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

      $row = 2;
      $this->write_excel_row($sheet, $row++, $header);

      $query = $this->dpo_model->get_bed_mutasi($start_date, $end_date);
      // $query = $this->dpo_model->get_bed_mutasi('2026-01-01', '2026-01-15');

      if ($query->num_rows() > 0) {
        foreach ($query->result_array() as $value) {
          $ipd_id = $value['ipd_id'];

          $tabel_obat = '';
          $pharmacy_resep_date = $this->amr_model->pharmacy_dpo_finished_mutasi($ipd_id);

          if ($pharmacy_resep_date->num_rows() > 0) {
            $list_obat = array();
            foreach ($pharmacy_resep_date->result_array() as $obat) {
              $list_obat[] = $this->clean_newlines($obat['nama_obat'], ' ') . '(' . $obat['pharmacy_resep_started'] . ')';
            }
            $tabel_obat = implode(",", $list_obat);
          }

          $data = array(
            $value['patient_id'],
            $value['case_reference_id'],
            $value['ipd_id'],
            $value['registration_date'],
            $value['discharge_date'],
            $value['patient_name'],
            $value['tanggal_lahir'],
            $value['patient_age'],
            $value['bed_name'],
            $value['bed_name_group'],
            $value['from_date'],
            $value['to_date'],
            $value['total_hari'],
            $tabel_obat
          );

          $this->write_excel_row($sheet, $row++, $data);
        }
      }

      $file_name_xlsx = 'Laporan Bed Mutasi DPO ' . $start_date . ' - ' . $end_date . '.xlsx';

      $last_data_row = $row - 1;
      $border_style = array(
        'borders' => array(
          'allBorders' => array(
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => array('argb' => 'FF000000')
          )
        )
      );
      if ($last_data_row >= 2) {
        $sheet->getStyle('A2:' . $sheet->getHighestColumn() . $last_data_row)->applyFromArray($border_style);
      }

      $header_row = 2;
      $sheet->getStyle('A' . $header_row . ':' . $sheet->getHighestColumn() . $header_row)
        ->getFont()->setBold(TRUE);
      $sheet->getStyle('A' . $header_row . ':' . $sheet->getHighestColumn() . $header_row)
        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

      $col_obat = $sheet->getColumnDimensionByColumn(13);
      $col_obat->setWidth(60);
      foreach ($sheet->getColumnIterator() as $column) {
        $col = $column->getColumnIndex();
        $rec = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col);
        if ($rec == 13) {
          continue;
        }
        $sheet->getColumnDimension($col)->setAutoSize(TRUE);
      }

      $sheet->getStyle('A2:' . $sheet->getHighestColumn() . $last_data_row)
        ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

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


/* End of file Dpox.php */
/* Location: ./application/controllers/Dpox.php */