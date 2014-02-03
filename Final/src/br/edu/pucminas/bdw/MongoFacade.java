package br.edu.pucminas.bdw;

import java.util.Map;

import net.sf.json.JSONArray;
import net.sf.json.JSONNull;
import net.sf.json.JSONObject;

import com.mongodb.BasicDBObject;
import com.mongodb.DB;
import com.mongodb.DBCollection;
import com.mongodb.Mongo;

/**
 * Classe respons�vel por realizar a comunica��o com o MongoDB.
 * 
 * @author Tiago Romero Garcia
 */
public class MongoFacade {

	private DB db;

	public MongoFacade() throws Exception {
		Mongo mongoDb = new Mongo("localhost", 27017);
		db = mongoDb.getDB("bdw");
	}

	/**
	 * Realiza a inser��o de tweets em formato JSON no MongoDB.
	 * 
	 * @param searchObject
	 *            objeto contendo os resultados da consulta
	 */
	public void insertTweets(JSONObject searchObject) {
		DBCollection tweetsCollection = db.getCollection("tweets");
		JSONArray resultsArray = searchObject.getJSONArray("results");

		int resultsLength = resultsArray.size();
		for (int index = 0; index < resultsLength; index++) {
			JSONObject tweetJSONObject = resultsArray.getJSONObject(index);
			BasicDBObject tweetDBObject = new BasicDBObject(tweetJSONObject);

			// Elimina��o de objetos nulos
			for (Map.Entry<String, Object> entry : tweetDBObject.entrySet()) {
				if (entry.getValue() instanceof JSONNull) {
					entry.setValue(null);
				}
			}

			tweetsCollection.insert(tweetDBObject);
		}
	}
}
