document.addEventListener("DOMContentLoaded", function () {
    const pieCtx = document.getElementById("pieChart")?.getContext("2d");
    const barCtx = document.getElementById("barChart")?.getContext("2d");

    if (pieCtx && barCtx && window.chartData) {
        new Chart(pieCtx, {
            type: "pie",
            data: {
                labels: window.chartData.labels,
                datasets: [{
                    data: window.chartData.values,
                    backgroundColor: ["#4dc9f6", "#f67019", "#f53794", "#537bc4", "#acc236"]
                }]
            }
        });

        new Chart(barCtx, {
            type: "bar",
            data: {
                labels: window.chartData.labels,
                datasets: [{
                    label: "Usage Count",
                    data: window.chartData.values,
                    backgroundColor: "#537bc4"
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }
});
