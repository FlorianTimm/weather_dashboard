export type Numberish = number | string;

export interface LiveApiResponse {
    current: CurrentWeather;
    calculated: CalculatedWeather;
    records: WeatherRecords;
}

export interface CurrentWeather {
    zeitstempel: string;
    intem: Numberish;
    inhum: Numberish;
    t1tem: Numberish;
    t1hum: Numberish;
    t1wdir: Numberish;
    t1raindy: Numberish;
    t1rainra: Numberish;
    t1ws: Numberish;
    t1wgust: Numberish;
    t1ws10mav: Numberish;
    t1solrad: Numberish;
}

export interface CalculatedWeather {
    wind_speed_kmh: Numberish;
    solar_theo: Numberish;
    cloudiness: Numberish;
    af_in: Numberish;
    af_out: Numberish;
    laerm_index: Numberish;
    traffic_noise: Numberish;
    traffic_flow: Numberish;
    schall_leitung: Numberish;
}

export interface WeatherRecord {
    val: Numberish;
    date: string;
}

export interface WeatherRecords {
    max_temp: WeatherRecord;
    min_temp: WeatherRecord;
    max_wind: WeatherRecord;
    max_rain: WeatherRecord;
}

export type RangeId = "24h" | "7d" | "30d";
export type AxisId = "temperature" | "wind" | "rain" | "percent" | "solar";
export type SeriesId =
    | "temp"
    | "temp_in"
    | "wind"
    | "rain_rate"
    | "traffic_flow"
    | "traffic_noise"
    | "dew_in"
    | "dew_out"
    | "hum_in"
    | "solar_theo"
    | "solar_meas"
    | "cloudiness";

export type ChartDataKey = SeriesId | "wind_dir";

export interface ChartPayload extends Record<ChartDataKey, Array<number | null>> {
    labels: string[];
    timestamps: number[];
}
