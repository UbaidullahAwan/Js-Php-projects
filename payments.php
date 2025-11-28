<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Options</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: #f5f7fa;
            color: #333;
        }

        /* Sidebar container */
        #sidebar-container {
            width: 250px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        /* Main Content Styles */
        .main-content {
            margin-top: 50px;
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h2 {
            color: #2d3748;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .header p {
            color: #718096;
            font-size: 0.7rem;
        }

        /* Payment Content Styles */
        .payment-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 30px;
        }

        .payment-header {
            margin-bottom: 25px;
            text-align: center;
        }

        .payment-header h3 {
            color: #2d3748;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .payment-header p {
            color: #718096;
            max-width: 600px;
            margin: 0 auto;
        }

        .bank-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .bank-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .bank-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .bank-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .bank-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .bank-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .bank-icon.mzn {
            background-color: #00a64f;
        }

        .bank-icon.hbl {
            background-color: #0066b3;
        }

        .bank-icon.ubl {
            background-color: #9c1d2a;
        }

        .bank-icon.bi {
            background-color: #00a79d;
        }

        .bank-name {
            font-weight: 600;
            font-size: 1rem;
            color: #2d3748;
        }

        .bank-details {
            margin-top: 15px;
        }

        .detail-row {
            margin-bottom: 12px;
            display: flex;
        }

        .detail-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #4a5568;
            min-width: 120px;
        }

        .detail-value {
            color: #2d3748;
            flex: 1;
        }

        .copy-btn {
            background-color: #4299e1;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 10px;
            transition: background-color 0.2s;
        }

        .copy-btn:hover {
            background-color: #3182ce;
        }

        .instructions {
            background-color: #f7fafc;
            border-left: 4px solid #4299e1;
            padding: 20px;
            margin-top: 30px;
            border-radius: 0 8px 8px 0;
        }

        .instructions h4 {
            margin-bottom: 10px;
            color: #2d3748;
        }

        .instructions ul {
            padding-left: 20px;
            color: #4a5568;
        }

        .instructions li {
            margin-bottom: 8px;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: #718096;
            font-size: 0.9rem;
            border-top: 1px solid #e2e8f0;
            margin-top: 30px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #2d3748;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #718096;
        }

        .modal-bank-details {
            margin-top: 20px;
        }

        .modal-detail-row {
            margin-bottom: 15px;
            display: flex;
        }

        .modal-detail-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 120px;
        }

        .modal-detail-value {
            color: #2d3748;
            flex: 1;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            #sidebar-container {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .bank-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar will be loaded here from sidebar.php -->
    <div id="sidebar-container"></div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2>Payment Options</h2>
            <p>Manage your payment methods and view bank account details</p>
        </div>

        <div class="payment-container">
            <div class="payment-header">
                <h3>Bank Account Details</h3>
                <p>Use the following bank account details for making payments. Please include your booking reference in the transaction details.</p>
            </div>

            <div class="bank-cards">
               

                <!-- HBL -->
                <div class="bank-card">
                    <div class="bank-header">
                        <div class="bank-icon hbl" data-bank="hbl">H</div>
                        <div class="bank-name">Habib Bank Limited – Islamic Banking</div>
                    </div>
                    <div class="bank-details">
                        <div class="detail-row">
                            <div class="detail-label">Account Title:</div>
                            <div class="detail-value">Hussain International</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Account Number: </div>
                            <div class="detail-value ">00040981067873010</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">IBAN:</div>
                            <div class="detail-value">PK35BAHL0004098106787301</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Address:</div>
                            <div class="detail-value">First Floor, NBP bank, Lowerplate, Muzaffarabad</div>
                        </div>
                    </div>
                </div>

                <!-- UBL -->
                

                <!-- BankIslami -->
                <div class="bank-card">
                    <div class="bank-header">
                        <div class="bank-icon bi" data-bank="bankislami">B</div>
                        <div class="bank-name">BankIslami</div>
                    </div>
                    <div class="bank-details">
                        <div class="detail-row">
                            <div class="detail-label">Account Title:</div>
                            <div class="detail-value">Hussain International Tourism Services (Pvt Ltd)</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Account Number:</div>
                            <div class="detail-value">305215703900201</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">IBAN:</div>
                            <div class="detail-value">PK39RKP0305215703900201</div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Address:</div>
                            <div class="detail-value">Shop No.9, Block 8, G-6 Melody Market, Civic Center, Islamabad</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="instructions">
                <h4>Payment Instructions:</h4>
                <ul>
                    <li>Please include your booking reference number in the transaction description</li>
                    <li>Send the transaction receipt to payments info@husaininternational.com for confirmation</li>
                    <li>Payments are processed within 24 hours of receipt confirmation</li>
                    <li>For international transfers, ensure to include all intermediary bank charges</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>&copy; 2025 Hussain Group of Companies. All rights reserved.</p>
        </div>
    </div>

    <!-- Modal for bank details -->
    <div class="modal" id="bankModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalBankName">Bank Details</h3>
                <button class="close-btn" id="closeModal">&times;</button>
            </div>
            <div class="modal-bank-details" id="modalBankDetails">
                <!-- Bank details will be populated here -->
            </div>
        </div>
    </div>

    <script>
        // Fetch sidebar.php and insert it into the container
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch sidebar.php
            fetch('sidebar.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    // Insert the sidebar HTML into the container
                    document.getElementById('sidebar-container').innerHTML = data;
                    
                    // Highlight the active payment link
                    const paymentLink = document.querySelector('a[href="payments.php"]');
                    if (paymentLink) {
                        paymentLink.classList.add('active');
                    }
                })
                .catch(error => {
                    console.error('Error loading sidebar:', error);
                    // Fallback: Create a basic sidebar if the fetch fails
                    document.getElementById('sidebar-container').innerHTML = `
                        <div class="sidebar" style="background: #1a3a5f; color: white; padding: 20px; height: 100vh;">
                            <h3>Hussain Group</h3>
                            <ul>
                                <li><a href="payments.php" style="color: white;">Payments</a></li>
                            </ul>
                        </div>
                    `;
                });

            // Set up event listeners for bank icons and copy buttons
            setupEventListeners();
        });

        // Function to set up event listeners
        function setupEventListeners() {
            // Bank icon click handlers
            document.querySelectorAll('.bank-icon').forEach(icon => {
                icon.addEventListener('click', function() {
                    const bank = this.getAttribute('data-bank');
                    showBankModal(bank);
                });
            });

            // Modal close button
            document.getElementById('closeModal').addEventListener('click', function() {
                document.getElementById('bankModal').style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('bankModal');
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Copy buttons for account numbers and IBANs
            document.querySelectorAll('.detail-value').forEach(element => {
                const text = element.textContent;
                if (text.includes('-') || text.includes('PK')) {
                    const button = document.createElement('button');
                    button.className = 'copy-btn';
                    button.innerHTML = '<i class="fas fa-copy"></i>';
                    button.title = 'Copy to clipboard';
                    
                    button.addEventListener('click', () => {
                        navigator.clipboard.writeText(text).then(() => {
                            button.innerHTML = '<i class="fas fa-check"></i>';
                            setTimeout(() => {
                                button.innerHTML = '<i class="fas fa-copy"></i>';
                            }, 2000);
                        });
                    });
                    
                    element.appendChild(button);
                }
            });
        }

        // Function to show bank details in modal
        function showBankModal(bank) {
            const modal = document.getElementById('bankModal');
            const modalBankName = document.getElementById('modalBankName');
            const modalBankDetails = document.getElementById('modalBankDetails');
            
            let bankName = '';
            let bankDetails = '';
            
            // Set bank details based on which bank was clicked
            switch(bank) {
                        case 'hbl':
                    bankName = 'Habib Bank Limited – Islamic Banking';
                    bankDetails = `
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">Account Title:</div>
                            <div class="modal-detail-value">Hussain International</div>
                        </div>
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">Account Number:</div>
                            <div class="modal-detail-value">00040981067873010</div>
                        </div>
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">IBAN:</div>
                            <div class="modal-detail-value">PK35BAHL0004098106787301</div>
                        </div>
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">Address:</div>
                            <div class="modal-detail-value">First Floor, NBP bank, LowerPlate,Muzaffarabad.</div>
                        </div>
                    `;
                    break;
                
                case 'bankislami':
                    bankName = 'BankIslami';
                    bankDetails = `
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">Account Title:</div>
                            <div class="modal-detail-value">Hussain International Tourism Services (Pvt Ltd)</div>
                        </div>
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">Account Number:</div>
                            <div class="modal-detail-value">305215703900201</div>
                        </div>
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">IBAN:</div>
                            <div class="modal-detail-value">PK39RKP0305215703900201</div>
                        </div>
                        <div class="modal-detail-row">
                            <div class="modal-detail-label">Address:</div>
                            <div class="modal-detail-value">Shop No.9, Block 8, G-6 Melody Market, Civic Center, Islamabad</div>
                        </div>
                    `;
                    break;
            }
            
            modalBankName.textContent = bankName;
            modalBankDetails.innerHTML = bankDetails;
            modal.style.display = 'flex';
        }
    </script>
</body>
</html>