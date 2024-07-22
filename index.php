<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=UTF-8');

try {
    // Create a new PDO instance to connect to the SQLite database
    $pdo = new PDO('sqlite:products.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle POST requests for adding new products
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addProduct'])) {
        $name = $_POST['productName'];
        $quantity = $_POST['productQuantity'];
        $stmt = $pdo->prepare('INSERT INTO products (name, quantity, ordered, sent) VALUES (:name, :quantity, 0, 0)');
        $stmt->execute([':name' => $name, ':quantity' => $quantity]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Handle PUT requests for updating products
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('UPDATE products SET name = :name, quantity = :quantity, ordered = :ordered, sent = :sent WHERE id = :id');
        $stmt->execute([
            ':id' => $data['id'],
            ':name' => $data['name'],
            ':quantity' => $data['quantity'],
            ':ordered' => $data['ordered'],
            ':sent' => $data['sent']
        ]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Handle DELETE requests for deleting products
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $data['id']]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    // Fetch all products for the initial page load
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query('SELECT * FROM products');
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($products);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi des Produits</title>
    <style>
        /* Add your styles here */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            position: relative;
            background-color: #f0f0f0;
            color: #333;
            z-index: 0;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('mas.png') no-repeat center center fixed;
            background-size: contain;
            opacity: 0.5;
            z-index: -1;
        }
        h1 {
            text-align: center;
            color: #007bff;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 10px;
            border-radius: 5px;
        }
        form {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 5px;
        }
        form input, form button {
            padding: 10px;
            font-size: 16px;
            margin: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 15px;
            text-align: center;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        .button {
            padding: 10px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            font-size: 14px;
        }
        .green {
            background-color: green;
            color: white;
        }
        .red {
            background-color: red;
            color: white;
        }
        .export-buttons, .import-section {
            text-align: center;
            margin-top: 20px;
        }
        .export-buttons button, .import-section input {
            margin-right: 10px;
            padding: 10px 20px;
        }
        .delete-button {
            background-color: #dc3545;
            color: white;
        }
        .import-section {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>Suivi des Produits</h1>
    <form id="productForm" method="POST">
        <input type="text" name="productName" placeholder="Nom du Produit" required>
        <input type="number" name="productQuantity" value="1" required min="1">
        <button type="submit" name="addProduct" class="button">Ajouter Produit</button>
    </form>
    <table id="productTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Commandé</th>
                <th>Envoyé</th>
                <th>Supprimer</th>
            </tr>
        </thead>
        <tbody>
            <!-- Les lignes de produits seront ajoutées ici -->
        </tbody>
    </table>
    <div class="export-buttons">
        <button id="exportPDF" class="button">Exporter en PDF</button>
        <button id="exportExcel" class="button">Exporter en Excel</button>
    </div>
    <div class="import-section">
        <input type="file" id="importExcel" accept=".xlsx" class="button">
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const productTableBody = document.querySelector('#productTable tbody');
            const productForm = document.getElementById('productForm');

            function fetchProducts() {
                fetch('index.php')
                    .then(response => response.json())
                    .then(data => {
                        productTableBody.innerHTML = '';
                        data.forEach(product => renderProduct(product));
                    })
                    .catch(error => console.error('Error fetching products:', error));
            }

            function addProduct(name, quantity) {
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        addProduct: '1',
                        productName: name,
                        productQuantity: quantity
                    })
                })
                .then(response => response.json())
                .then(() => fetchProducts())
                .catch(error => console.error('Error adding product:', error));
            }

            function updateProduct(product) {
                fetch('index.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(product)
                })
                .catch(error => console.error('Error updating product:', error));
            }

            function deleteProduct(id) {
                fetch('index.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                })
                .catch(error => console.error('Error deleting product:', error));
            }

            function renderProduct(product) {
                const row = document.createElement('tr');
                row.setAttribute('data-id', product.id);
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td>${product.name}</td>
                    <td>${product.quantity}</td>
                    <td><button class="toggle ordered ${product.ordered ? 'green' : 'red'}">${product.ordered ? 'Commandé' : 'Non Commandé'}</button></td>
                    <td><button class="toggle sent ${product.sent ? 'green' : 'red'}">${product.sent ? 'Envoyé' : 'Non Envoyé'}</button></td>
                    <td><button class="delete-button button">Supprimer</button></td>
                `;
                productTableBody.appendChild(row);

                row.querySelectorAll('.toggle').forEach(button => {
                    button.addEventListener('click', () => {
                        const field = button.classList.contains('ordered') ? 'ordered' : 'sent';
                        product[field] = !product[field];
                        updateProduct(product);
                        button.classList.toggle('red');
                        button.classList.toggle('green');
                        button.textContent = product[field] ? (field === 'ordered' ? 'Commandé' : 'Envoyé') : (field === 'ordered' ? 'Non Commandé' : 'Non Envoyé');
                    });
                });

                row.querySelector('.delete-button').addEventListener('click', () => {
                    deleteProduct(product.id);
                    row.remove();
                });
            }

            productForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const name = productForm.querySelector('input[name="productName"]').value;
                const quantity = productForm.querySelector('input[name="productQuantity"]').value;
                addProduct(name, quantity);
                productForm.reset();
            });

            document.getElementById('exportPDF').addEventListener('click', () => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                doc.text('Liste des Produits', 14, 16);

                const table = document.getElementById('productTable');
                const rows = Array.from(table.querySelectorAll('tr')).map(row => Array.from(row.querySelectorAll('td')).map(cell => cell.textContent));

                doc.autoTable({
                    head: [['ID', 'Produit', 'Quantité', 'Commandé', 'Envoyé', 'Supprimer']],
                    body: rows.slice(1) // Skip header row
                });

                doc.save('products.pdf');
            });

            document.getElementById('exportExcel').addEventListener('click', () => {
                const table = document.getElementById('productTable');
                const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
                XLSX.writeFile(wb, 'products.xlsx');
            });

            fetchProducts();
        });
    </script>
</body>
</html>
