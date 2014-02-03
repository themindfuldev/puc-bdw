package br.edu.pucminas.bdw;

import net.sf.json.JSONObject;

/**
 * Classe de entrada do sistema, que coordena o processo de coleta de tweets e
 * gravação no MongoDB.
 * 
 * @author Tiago Romero Garcia
 */
public class Main {

	private static TwitterFacade twitterCollector;
	private static MongoFacade mongoFacade;

	/**
	 * Método de entrada do sistema, responsável por iniciar e finalizar todo o
	 * processo.
	 * 
	 * @param args
	 */
	public static void main(String[] args) {
		try {
			twitterCollector = new TwitterFacade();
			mongoFacade = new MongoFacade();

			collectAndStore("Fiat Marea");
			collectAndStore("\"Novo Palio\"");

			twitterCollector.close();
		} catch (Exception e) {
			e.printStackTrace();
		}

	}

	/**
	 * Coleta tweets e os armazena no MongoDB para um termo específico.
	 * 
	 * @param query
	 *            termo a ser coletado
	 * @throws Exception
	 */
	private static void collectAndStore(String query) throws Exception {
		// Coletar do Twitter
		System.out.print("Coletando por: " + query + "...");
		JSONObject searchObject = twitterCollector.collect(query);
		System.out.println(" Pronto!");

		// Armazenar no MongoDB
		System.out.print("Armazenando no MongoDB...");
		mongoFacade.insertTweets(searchObject);
		System.out.println(" Pronto!");
	}

}
