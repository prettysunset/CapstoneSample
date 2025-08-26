<?php
require 'conn.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Table headers
$headers = ["Time","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"];
$colorCols = ["monday_color","tuesday_color","wednesday_color","thursday_color","friday_color","saturday_color"];

// Handle Excel upload
if(isset($_POST['submit'])){
    if(isset($_FILES['excel_file']) && $_FILES['excel_file']['error']==0){
        $filePath = $_FILES['excel_file']['tmp_name'];
        try{
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $conn->query("TRUNCATE TABLE excel");

            foreach($sheet->getRowIterator() as $rowIndex=>$row){
                if($rowIndex===1) continue; // skip header
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                $values = [];
                foreach($cellIterator as $cell){
                    $value = $cell->getValue() ?? '';
                    if(Date::isDateTime($cell)){
                        $value = Date::excelToDateTimeObject($value)->format('H:i');
                    }
                    $values[] = $value;
                }
                while(count($values)<7) $values[]='';
                $stmt = $conn->prepare(
                    "INSERT INTO excel (time,monday,tuesday,wednesday,thursday,friday,saturday) VALUES (?,?,?,?,?,?,?)"
                );
                $stmt->bind_param("sssssss",$values[0],$values[1],$values[2],$values[3],$values[4],$values[5],$values[6]);
                $stmt->execute();
            }
            header("Location: dashboard.php");
            exit;
        } catch(Exception $e){
            echo "<p style='color:red;'>Error reading Excel file: ".$e->getMessage()."</p>";
        }
    } else{
        echo "<p style='color:red;'>Please upload a valid Excel file.</p>";
    }
}

// Handle AJAX actions
if(isset($_POST['action'])){
    $id = $_POST['id'] ?? 0;

    if($_POST['action']==='save_all'){
        $changes = $_POST['changes'];
        foreach($changes as $rowId=>$cols){
            $set = []; $types=''; $values=[];
            foreach($cols as $col=>$val){
                $set[]="$col=?";
                $types.='s';
                $values[]=$val;
            }
            $values[]=$rowId;
            $types.='i';
            $sql="UPDATE excel SET ".implode(',',$set)." WHERE id=?";
            $stmt=$conn->prepare($sql);
            $stmt->bind_param($types,...$values);
            $stmt->execute();
        }
        echo 'success';
        exit;
    }

    if($_POST['action']==='delete'){
        $stmt=$conn->prepare("DELETE FROM excel WHERE id=?");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        echo 'success';
        exit;
    }
}

// Fetch existing data
$result=$conn->query("SELECT * FROM excel");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Excel Upload</title>
<style>
table{border-collapse:collapse;width:100%;margin-top:20px;}
table,th,td{border:1px solid black;padding:8px;text-align:center;}
th{background-color:#f0f8ff;}
button{padding:4px 8px;margin:2px;}
td[contenteditable]{min-width:80px;cursor:pointer;}
td.selected{outline:2px solid #FF0000;}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<a href="cl2.php" style="padding:6px 12px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:4px;">View CL2 Schedule</a>

<h2>Upload Excel File</h2>
<form method="POST" enctype="multipart/form-data">
<input type="file" name="excel_file" accept=".xls,.xlsx" required>
<button type="submit" name="submit">Upload & Save</button>
</form>

<h3>Table Data:</h3>
<button onclick="addRow()">Add Row</button>
<table id="dataTable">
<thead>
<tr>
<?php foreach($headers as $header): ?>
<th><?php echo htmlspecialchars($header); ?></th>
<?php endforeach; ?>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if($result && $result->num_rows>0): ?>
<?php while($row=$result->fetch_assoc()): ?>
<tr data-id="<?php echo $row['id']; ?>">
<?php foreach(["time","monday","tuesday","wednesday","thursday","friday","saturday"] as $i=>$col): ?>
<?php $colorCol = $colorCols[$i-1] ?? ''; ?>
<td contenteditable="true" class="editable" data-column="<?php echo $col; ?>" 
    style="background-color: <?php echo !empty($row[$colorCol]) ? $row[$colorCol] : ''; ?>">
    <?php echo htmlspecialchars($row[$col]); ?>
</td>
<?php endforeach; ?>
<td>
<button class="deleteBtn">Delete</button>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="<?php echo count($headers)+1; ?>">No data available</td></tr>
<?php endif; ?>
</tbody>
</table>

<script>
let headers=["time","monday","tuesday","wednesday","thursday","friday","saturday"];
let colorCols=["monday_color","tuesday_color","wednesday_color","thursday_color","friday_color","saturday_color"];
let changes={};
let isSelecting=false;

// Add new row
function addRow(){
    let table=document.getElementById('dataTable').getElementsByTagName('tbody')[0];
    let row=table.insertRow();
    row.setAttribute('data-id',0);
    headers.forEach(h=>{
        let cell=row.insertCell();
        cell.contentEditable="true";
        cell.className="editable";
        cell.setAttribute("data-column",h);
        cell.innerText="";
    });
    let actionCell=row.insertCell();
    let delBtn=document.createElement('button'); delBtn.innerText="Delete"; delBtn.className="deleteBtn";
    actionCell.appendChild(delBtn);
}

// Convert RGB to HEX
function rgbToHex(rgb) {
    let result = rgb.match(/\d+/g);
    if(!result) return '';
    return "#" + ((1 << 24) + (parseInt(result[0]) <<16) + (parseInt(result[1]) <<8) + parseInt(result[2])).toString(16).slice(1).toUpperCase();
}

// Track cell content changes
$(document).on('input','td.editable',function(){
    let tr=$(this).closest('tr');
    let rowId=tr.data('id');
    let col=$(this).data('column');
    if(!changes[rowId]) changes[rowId]={};
    changes[rowId][col]=$(this).text();
});

// Track highlight color changes
function saveCellColor(cell){
    let tr=$(cell).closest('tr');
    let rowId=$(tr).data('id');
    let col=$(cell).data('column');
    if(col=="time") return;
    let idx=headers.indexOf(col)-1;
    if(idx<0) return;
    let colorCol=colorCols[idx];
    if(!changes[rowId]) changes[rowId]={};
    let bg=$(cell).css('background-color');
    changes[rowId][colorCol]=rgbToHex(bg);
}

// Selection
$(document).on('mousedown','td.editable',function(e){
    isSelecting=true;
    if(!e.ctrlKey) $('td.selected').removeClass('selected');
    $(this).addClass('selected');
});

$(document).on('mouseover','td.editable',function(){
    if(isSelecting) $(this).addClass('selected');
});

$(document).on('mouseup',function(){ isSelecting=false; });

// Ctrl + H to highlight
$(document).on('keydown',function(e){
    if(e.ctrlKey && e.key.toLowerCase()==='h'){
        e.preventDefault();
        $('td.selected').each(function(){
            $(this).css('background-color','#FFD700');
            saveCellColor(this);
            $(this).removeClass('selected');
        });
    }
    // Ctrl + S to save all changes
    if(e.ctrlKey && e.key.toLowerCase()==='s'){
        e.preventDefault();
        $.post('dashboard.php',{action:'save_all',changes:changes},function(resp){
            alert('All changes saved!');
            changes={};
        });
    }
});

// Delete button
$(document).on('click','.deleteBtn',function(){
    if(!confirm('Are you sure?')) return;
    let tr=$(this).closest('tr');
    let id=tr.data('id');
    if(id==0){tr.remove(); return;}
    $.post('dashboard.php',{action:'delete',id:id},function(resp){
        if(resp==='success') tr.remove();
    });
});
</script>
</body>
</html>
