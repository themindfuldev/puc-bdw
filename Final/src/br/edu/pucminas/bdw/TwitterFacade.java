package br.edu.pucminas.bdw;

import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.FileWriter;
import java.io.IOException;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;

import net.sf.json.JSONObject;

/**
 * Classe responsável por realizar a comunicação com o Twitter.
 * 
 * @author Tiago Romero Garcia
 */
public class TwitterFacade {

	private static int RESULTS_PER_PAGE = 1000;
	private static String BASE_URL = "http://search.twitter.com/search.json?rpp="
			+ RESULTS_PER_PAGE + "&q=";
	private static String FILE_NAME = "exemploDasColetas.txt";

	private BufferedWriter fileWriter;

	public TwitterFacade() throws IOException {
		fileWriter = new BufferedWriter(new FileWriter(FILE_NAME));
	}

	/**
	 * Coleta tweets para um termo específico.
	 * 
	 * @param query
	 *            termo a ser coletado
	 * @return objeto JSON contendo os resultados da busca
	 * @throws Exception
	 */
	public JSONObject collect(String query) throws Exception {
		String encodedQuery = URLEncoder.encode(query, "UTF-8");
		String responseString = httpGet(BASE_URL + encodedQuery); 
		JSONObject responseObject = JSONObject.fromObject(responseString);
		log("Consulta realizada: " + BASE_URL + encodedQuery + "\n");
		log("Resultado:\n" + responseObject.toString(3));
		log("\n--------------------------------------------------------------------------------------\n");
		return responseObject;
	}

	/**
	 * Realiza uma chamada HTTP GET em uma URL.
	 * 
	 * @param urlToRead
	 *            URL a ser requisitada
	 * @return resposta obtida
	 * @throws Exception
	 */
	private String httpGet(String urlToRead) throws Exception {
		StringBuilder result = new StringBuilder();
		String line = null;
		URL url = new URL(urlToRead);
		HttpURLConnection connection = (HttpURLConnection) url.openConnection();
		connection.setRequestMethod("GET");
		BufferedReader rd = new BufferedReader(new InputStreamReader(
				connection.getInputStream()));
		while ((line = rd.readLine()) != null) {
			result.append(line);
		}
		rd.close();
		return result.toString();
	}

	/**
	 * Realiza uma gravação no arquivo de texto.
	 * 
	 * @param string
	 *            texto a ser gravado
	 * @throws IOException
	 */
	private void log(String string) throws IOException {
		fileWriter.append(string);
	}

	public void close() throws IOException {
		fileWriter.close();
	}

}
