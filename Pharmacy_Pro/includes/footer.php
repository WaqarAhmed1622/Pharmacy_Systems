</div>
        </div>
    </div>
    
    <!-- Embedded jQuery (Offline) -->
    <script>
        /* jQuery 3.6.0 Minimal - Embedded for Offline Use */
        !function(e,t){"use strict";"object"==typeof module&&"object"==typeof module.exports?module.exports=e.document?t(e,!0):function(e){if(!e.document)throw new Error("jQuery requires a window with a document");return t(e)}:t(e)}("undefined"!=typeof window?window:this,function(C,e){"use strict";var t=[],r=Object.getPrototypeOf,s=t.slice,g=t.flat?function(e){return t.flat.call(e)}:function(e){return t.concat.apply([],e)},u=t.push,i=t.indexOf,n={},o=n.toString,v=n.hasOwnProperty,a=v.            </div>
        </div>
    </div>
    
    <!-- Simple JavaScript for Basic Functionality (Offline) -->
    <script>
        // Simple jQuery-like functions for offline use
        function $(selector) {
            if (typeof selector === 'function') {
                document.addEventListener('DOMContentLoaded', selector);
                return;
            }
            
            var elements = document.querySelectorAll(selector);
            var result = {
                elements: elements,
                length: elements.length,
                
                on: function(event, handler) {
                    elements.forEach(function(el) {
                        el.addEventListener(event, handler);
                    });
                    return this;
                },
                
                val: function(value) {
                    if (value !== undefined) {
                        elements.forEach(function(el) {
                            el.value = value;
                        });
                        return this;
                    }
                    return elements[0] ? elements[0].value : '';
                },
                
                focus: function() {
                    if (elements[0]) elements[0].focus();
                    return this;
                },
                
                click: function() {
                    if (elements[0]) elements[0].click();
                    return this;
                },
                
                fadeOut: function() {
                    elements.forEach(function(el) {
                        el.style.transition = 'opacity 0.5s';
                        el.style.opacity = '0';
                        setTimeout(function() {
                            el.style.display = 'none';
                        }, 500);
                    });
                    return this;
                },
                
                closest: function(selector) {
                    if (elements[0]) {
                        var closest = elements[0].closest(selector);
                        return closest ? $([closest]) : $([]);
                    }
                    return $([]);
                },
                
                find: function(selector) {
                    var found = [];
                    elements.forEach(function(el) {
                        var children = el.querySelectorAll(selector);
                        found = found.concat(Array.from(children));
                    });
                    return $(found);
                },
                
                each: function(callback) {
                    elements.forEach(callback);
                    return this;
                },
                
                addClass: function(className) {
                    elements.forEach(function(el) {
                        el.classList.add(className);
                    });
                    return this;
                },
                
                text: function(text) {
                    if (text !== undefined) {
                        elements.forEach(function(el) {
                            el.textContent = text;
                        });
                        return this;
                    }
                    return elements[0] ? elements[0].textContent : '';
                },
                
                data: function(attr) {
                    return elements[0] ? elements[0].getAttribute('data-' + attr) : null;
                }
            };
            
            // Handle array of elements
            if (Array.isArray(selector)) {
                result.elements = selector;
                result.length = selector.length;
            }
            
            return result;
        }
        
        // Document ready equivalent
        $(function() {
            // Auto-focus barcode input fields
            $('.barcode-input').focus();
            
            // Auto-submit barcode form on Enter
            $('.barcode-input').on('keypress', function(e) {
                if (e.which === 13) {
                    var form = e.target.closest('form');
                    if (form) form.submit();
                }
            });
            
            // Confirmation dialogs
            $('.delete-btn').on('click', function(e) {
                if (!confirm('Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
            
            // Format currency inputs
            $('.currency-input').on('input', function() {
                var value = this.value.replace(/[^0-9.]/g, '');
                this.value = value;
            });
            
            // Stock level warnings
            $('.stock-quantity').each(function(el) {
                var stock = parseInt(el.textContent);
                var minStock = parseInt($(el).data('min-stock'));
                
                if (stock <= 0) {
                    $(el.closest('tr')).addClass('out-of-stock');
                } else if (stock <= minStock) {
                    $(el.closest('tr')).addClass('low-stock');
                }
            });
        });
        
        // Barcode scanner simulation (for testing)
        function simulateBarcodeScan(barcode) {
            var input = document.querySelector('.barcode-input');
            if (input) {
                input.value = barcode;
                var event = new Event('change', { bubbles: true });
                input.dispatchEvent(event);
            }
        }
        
        // Print function for receipts
        function printReceipt() {
            window.print();
        }
        
        // Simple AJAX helper function
        function ajaxRequest(url, data, callback) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            callback(response);
                        } catch (e) {
                            callback({ error: 'Invalid JSON response' });
                        }
                    } else {
                        alert('An error occurred. Please try again.');
                    }
                }
            };
            
            // Convert data object to URL encoded string
            var params = Object.keys(data).map(function(key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(data[key]);
            }).join('&');
            
            xhr.send(params);
        }
        
        // Simple chart implementation (replaces Chart.js)
        function createChart(canvas, config) {
            var ctx = canvas.getContext('2d');
            var data = config.data;
            var labels = data.labels;
            var dataset = data.datasets[0];
            var values = dataset.data;
            
            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Set canvas size
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight || 300;
            
            var padding = 40;
            var chartWidth = canvas.width - padding * 2;
            var chartHeight = canvas.height - padding * 2;
            
            // Find max value
            var maxValue = Math.max.apply(Math, values);
            if (maxValue === 0) maxValue = 1;
            
            // Draw axes
            ctx.strokeStyle = '#ccc';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(padding, padding);
            ctx.lineTo(padding, canvas.height - padding);
            ctx.lineTo(canvas.width - padding, canvas.height - padding);
            ctx.stroke();
            
            // Draw line chart
            if (values.length > 0) {
                ctx.strokeStyle = dataset.borderColor || '#007bff';
                ctx.fillStyle = dataset.backgroundColor || 'rgba(0,123,255,0.1)';
                ctx.lineWidth = 2;
                
                var stepX = chartWidth / (values.length - 1 || 1);
                
                // Fill area
                ctx.beginPath();
                ctx.moveTo(padding, canvas.height - padding);
                for (var i = 0; i < values.length; i++) {
                    var x = padding + i * stepX;
                    var y = canvas.height - padding - (values[i] / maxValue * chartHeight);
                    if (i === 0) ctx.lineTo(x, y);
                    else ctx.lineTo(x, y);
                }
                ctx.lineTo(canvas.width - padding, canvas.height - padding);
                ctx.closePath();
                ctx.fill();
                
                // Draw line
                ctx.beginPath();
                for (var i = 0; i < values.length; i++) {
                    var x = padding + i * stepX;
                    var y = canvas.height - padding - (values[i] / maxValue * chartHeight);
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                }
                ctx.stroke();
                
                // Draw points
                ctx.fillStyle = dataset.borderColor || '#007bff';
                for (var i = 0; i < values.length; i++) {
                    var x = padding + i * stepX;
                    var y = canvas.height - padding - (values[i] / maxValue * chartHeight);
                    ctx.beginPath();
                    ctx.arc(x, y, 3, 0, 2 * Math.PI);
                    ctx.fill();
                }
            }
            
            // Draw labels
            ctx.fillStyle = '#666';
            ctx.font = '12px Arial';
            ctx.textAlign = 'center';
            for (var i = 0; i < labels.length; i++) {
                var x = padding + i * stepX;
                ctx.fillText(labels[i], x, canvas.height - padding + 20);
            }
            
            // Draw Y-axis labels
            ctx.textAlign = 'right';
            for (var i = 0; i <= 5; i++) {
                var y = canvas.height - padding - (i / 5 * chartHeight);
                var value = (maxValue / 5 * i).toFixed(2);
                ctx.fillText(' + value, padding - 10, y + 4);
            }
        }
        
        // Chart.js compatibility layer
        var Chart = {
            register: function() {}, // No-op
        };
        
        // Create Chart constructor
        Chart = function(ctx, config) {
            if (typeof ctx === 'string') {
                ctx = document.getElementById(ctx);
            }
            if (ctx && ctx.getContext) {
                createChart(ctx, config);
            }
        };
        
        // Bootstrap-like functionality for alerts
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts
            var alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                var closeBtn = alert.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        alert.style.display = 'none';
                    });
                }
            });
        });
    </script>
</body>
</html>