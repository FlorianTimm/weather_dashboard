import { byId, setHtml, setStyle, setText } from "./dom";
import { formatFixed1, formatNumber } from "./format";
import type { LiveApiResponse, Numberish, WeatherRecord, WeatherRecords } from "./types";

export class LiveDashboard {
    init(): void {
        void this.update();
        window.setInterval(() => void this.update(), 60000);
    }

    private async update(): Promise<void> {
        try {
            const response = await fetch("api.php?action=live");
            const data = (await response.json()) as LiveApiResponse;

            this.renderLiveValues(data);
            this.renderRecords(data.records);
            this.renderKWL(
                data.current.intem,
                data.current.inhum,
                data.current.t1tem,
                data.calculated.af_in,
                data.calculated.af_out,
            );
            this.renderNoise(
                data.calculated.laerm_index,
                data.current.t1wdir,
                data.calculated.wind_speed_kmh,
                data.calculated.traffic_noise,
                data.calculated.schall_leitung,
            );
            this.renderDrone(data.current.t1wgust, data.current.t1ws10mav);
        } catch (error) {
            console.error("Live-Polling fehlgeschlagen", error);
        }
    }

    private renderLiveValues(data: LiveApiResponse): void {
        const { current, calculated } = data;
        const indoorHumidity = Number(current.inhum);
        const indoorTemp = Number(current.intem);
        const indoorHumidityOk = indoorHumidity >= 40 && indoorHumidity <= 55;

        setText("live-time", new Date(current.zeitstempel).toLocaleTimeString("de-DE"));
        setText("live-t-in", formatFixed1(current.intem));
        setStyle("live-t-in", "color", indoorTemp >= 20 && indoorTemp <= 22 ? "#2ecc71" : "#e67e22");
        setText("live-rh-in", `${current.inhum} %`);
        setText("live-t-out", formatFixed1(current.t1tem));
        setText("live-rh-out", `${current.t1hum} %`);
        setText("live-wdir", `${current.t1wdir}°`);
        setText("live-rain", formatFixed1(current.t1raindy));
        setText("live-rainrate", `${formatFixed1(current.t1rainra)} mm/h`);
        setText("live-ws", formatNumber(calculated.wind_speed_kmh));
        setText("live-bft", `${this.bftCalc(current.t1ws)} Bft (${formatNumber(current.t1ws)} m/s)`);
        setText("live-in-status", indoorHumidity < 40 ? "Zu trocken 🌵" : indoorHumidity > 55 ? "Feucht 💧" : "Optimal OK");
        setStyle("live-in-status", "color", indoorHumidityOk ? "#2ecc71" : "#e67e22");
        setText("live-solar", `${formatNumber(current.t1solrad)} W/m² (Soll: ${calculated.solar_theo})`);
        setText("live-cloud", `${calculated.cloudiness} %`);
    }

    private renderRecords(records: WeatherRecords): void {
        this.setRecord("rec-max-t", records.max_temp, "°C");
        this.setRecord("rec-min-t", records.min_temp, "°C");
        this.setRecord("rec-max-w", records.max_wind, "km/h", { maximumFractionDigits: 1 });
        this.setRecord("rec-max-r", records.max_rain, "mm");
    }

    private setRecord(id: string, record: WeatherRecord, unit: string, options: Intl.NumberFormatOptions = {}): void {
        setHtml(id, `${formatNumber(record.val, options)} ${unit} <div class='sub-date'>am ${record.date}</div>`);
    }

    private bftCalc(ms: Numberish): number | "6+" {
        const speed = Number(ms);
        if (speed < 0.3) return 0;
        if (speed < 1.6) return 1;
        if (speed < 3.4) return 2;
        if (speed < 5.5) return 3;
        if (speed < 8.0) return 4;
        if (speed < 10.8) return 5;
        return "6+";
    }

    private renderKWL(tinValue: Numberish, rhinValue: Numberish, toutValue: Numberish, afinValue: Numberish, afoutValue: Numberish): void {
        const tin = Number(tinValue);
        const rhin = Number(rhinValue);
        const tout = Number(toutValue);
        const afin = Number(afinValue);
        const afout = Number(afoutValue);
        let stufe = 2;
        let color = "#2cb67d";
        let text = "Normalbetrieb. Das Raumklima bewegt sich im gewünschten Bereich.";

        if (tout > 23.5 || (tout > tin && afout > afin)) {
            stufe = 1;
            color = "#e67e22";
            text = "Stufe 1 (Mindestlüftung). Draußen zu warm/schwül. Keine Hitze reinholen!";
        } else if (tout < tin && tin > 22.0 && afout < afin) {
            stufe = 3;
            color = "#3498db";
            text = "Stufe 3 (Intensivlüftung). Perfekt zum Abkühlen und Entfeuchten des Hauses!";
        } else if (tout < 8.0 && (rhin < 42 || afin < 7.5 || tin < 20.0)) {
            stufe = 1;
            color = "#9b59b6";
            text = "Stufe 1 (Frost-/Trockenschutz). Raumluft zu trocken (<42%) oder zu kalt. Lüftung minimieren!";
        } else if (rhin > 58.0 && afout < afin) {
            stufe = 3;
            color = "#e74c3c";
            text = "Stufe 3 (Intensivlüftung). Hohe Feuchte im Haus. Jetzt schnell ablüften!";
        }

        setStyle("kwl-card", "borderLeftColor", color);
        setStyle("kwl-badge", "backgroundColor", color);
        setText("kwl-badge", `Empfehlung: Stufe ${stufe}`);
        setText("kwl-text", `👉 ${text}`);
        setText("live-af-in", `${formatNumber(afin)} g/m³`);
        setText("live-af-out", `${formatNumber(afout)} g/m³`);
        setText("live-kwl-trend", afout < afin ? "⬇️ Entfeuchtend" : "⬆️ Befeuchtend");
        setStyle("live-kwl-trend", "color", afout < afin ? "#2ecc71" : "#e67e22");
    }

    private renderNoise(index: Numberish, wdir: Numberish, speed: Numberish, trafficNoise: Numberish, schallLeitung: Numberish): void {
        const noiseIndex = Number(index);
        let status = "Norddeutsche Gelassenheit";
        let desc = "Kaum merkliches Rauschen der Autobahnen.";
        let color = "#2ecc71";

        if (noiseIndex >= 30 && noiseIndex <= 60) {
            status = "Spürbares Hintergrundrauschen";
            desc = "Klassisches Autobahnbrummen im Garten wahrnehmbar.";
            color = "#f1c40f";
        } else if (noiseIndex > 60) {
            status = "Lautstärke-Maximum!";
            desc = "Der Wind steht ungünstig und trägt den Rollschall direkt zu dir.";
            color = "#e74c3c";
        }

        setStyle("noise-card", "borderLeftColor", color);
        setStyle("noise-badge", "backgroundColor", color);
        setText("noise-badge", `Lärm-Index: ${index} %`);
        setHtml("noise-text", `🔊 <strong>${status}</strong> — ${desc}`);
        setHtml("noise-factors", `
            <strong>Analysedaten:</strong> 
            Verkehrslautstärke-Koeffizient: <strong>${trafficNoise}%</strong> (Höher = Freier/Lauter) | 
            Schall-Ausbreitung: <strong>${schallLeitung}%</strong> (Wind-Vektor bei ${wdir}° / ${speed} km/h)
        `);
    }

    private renderDrone(gustValue: Numberish, avgValue: Numberish): void {
        const bar = byId<HTMLDivElement>("drone-bar");
        const gust = Number(gustValue);
        const avg = Number(avgValue);
        const gustKmh = gust * 3.6;
        const avgKmh = avg * 3.6;
        if (!bar) return;

        if (gust < 6.0 && avg < 5.0) {
            bar.innerText = "🟢 Perfekt zum Fliegen. Kaum Wind.";
            bar.style.backgroundColor = "#c6f6d5";
            bar.style.color = "#22543d";
        } else if (gust <= 8.5 && avg <= 7.0) {
            bar.innerText = "🟠 Risiko! Spürbarer Wind/Böen. Mini 2 kämpft.";
            bar.style.backgroundColor = "#feebc8";
            bar.style.color = "#744210";
        } else {
            bar.innerText = "🔴 Flugverbot! Zu starke Böen/Wind für die Mini 2.";
            bar.style.backgroundColor = "#fed7d7";
            bar.style.color = "#742a2a";
        }

        setHtml("drone-details", `
            <div style="text-align:left; padding:0 15px;"><strong>Wind aktuell:</strong> ${gustKmh.toFixed(1)} km/h</div>
            <div style="text-align:left; padding:0 15px;"><strong>Ø Wind (10 Min):</strong> ${avgKmh.toFixed(1)} km/h</div>
        `);
    }
}
